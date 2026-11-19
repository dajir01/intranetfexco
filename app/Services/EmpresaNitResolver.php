<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Empresa;
use App\Support\NitNormalizer;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class EmpresaNitResolver
{
    private const PROVISIONAL_MARKER = '__contrato_reserva_provisional__';

    private const NORMALIZED_NIT_SQL = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(nit), '-', ''), '.', ''), '/', ''), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(11), ''), CHAR(12), ''), CHAR(13), '')";

    private const CANDIDATE_FIELDS = [
        'id_empresa',
        'nit',
        'fecha_creacion',
        'fecha_actualizacion_empresa',
    ];

    private const SERIALIZED_COMPANY_FIELDS = [
        'id_empresa',
        'nombre_empresa',
        'nit',
        'direccion',
        'telefono',
        'fax',
        'email',
        'web',
        'pais',
        'ciudad',
        'nombre_responsable',
        'telefono_responsable',
        'email_representante',
        'nombre_gerente',
        'ci_gerente',
        'exp_ci_gerente',
        'fono_gerente',
        'cargo_gerente',
        'rubro',
        'subrubro',
        'otro_rubro',
        'cluster',
        'aniversario',
        'nr_escritura',
        'fecha_nr_escritura',
        'matricula',
        'nr_poder',
        'nr_notaria',
        'fecha_nr_poder',
        'distrito',
        'id_tipo_representante',
    ];

    private const COMPANY_REFERENCE_TABLES = [
        'contrato',
        'backedempresa',
        'cred_adicionales',
        'credencial_pin_envios',
        'credencial_pines',
        'credenciales',
        'empresa_evento_credenciales',
        'credencial_empresa_envios',
        'pago',
    ];

    public static function provisionalMarker(): string
    {
        return self::PROVISIONAL_MARKER;
    }

    public function withNitLock(string $nit, Closure $callback): mixed
    {
        $normalizedNit = NitNormalizer::normalize($nit);
        if (strlen($normalizedNit) < 5
            || ! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $callback();
        }

        $lockName = 'empresa_nit_'.substr(hash('sha256', $normalizedNit), 0, 52);
        // MySQL advisory locks serialize NIT writes without adding a database table.
        $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new RuntimeException('No se pudo reservar el NIT para guardar. Intente nuevamente.');
        }

        try {
            return $callback();
        } finally {
            $released = DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            if ((int) ($released->released ?? 0) !== 1) {
                throw new RuntimeException('No se pudo liberar el bloqueo temporal del NIT.');
            }
        }
    }

    public function lookup(string $nit): array
    {
        $normalizedNit = NitNormalizer::normalize($nit);
        if (strlen($normalizedNit) < 5) {
            throw ValidationException::withMessages([
                'nit' => 'Ingrese un NIT de al menos 5 dígitos para buscar la empresa.',
            ]);
        }

        [$company, $requiresReview] = $this->selectCandidate(
            $this->companiesForNitPreferringActive($normalizedNit)
        );
        if ($company === null) {
            return ['encontrada' => false, 'requiere_revision' => false];
        }

        if ($requiresReview) {
            return ['encontrada' => true, 'requiere_revision' => true];
        }

        $company = Empresa::query()->findOrFail(
            $company->id_empresa,
            self::SERIALIZED_COMPANY_FIELDS
        );

        return [
            'encontrada' => true,
            'requiere_revision' => false,
            'empresa' => $this->serializeCompany($company),
        ];
    }

    /**
     * Returns one selectable company per valid NIT. Records with no usable NIT
     * remain separate because matching them by name alone could merge distinct companies.
     *
     * @return array{
     *     companies: array<int, array<string, mixed>>,
     *     ambiguous_count: int,
     *     ambiguous_companies: array<int, array{nit: string, companies: array<int, array<string, mixed>>}>
     * }
     */
    public function canonicalReservationCompanies(): array
    {
        $companies = Empresa::query()
            ->where('es_activo', 1)
            ->select([
                'id_empresa',
                'nombre_empresa',
                'nombre_responsable',
                'nombre_gerente',
                'nit',
                'fecha_creacion',
                'fecha_actualizacion_empresa',
            ])
            ->orderBy('nombre_empresa')
            ->orderBy('id_empresa')
            ->get();

        $groups = $companies->groupBy(function (Empresa $company): string {
            $nit = NitNormalizer::normalize((string) $company->nit);

            return strlen($nit) >= 5
                ? 'nit:'.$nit
                : 'company:'.$company->id_empresa;
        });

        $canonical = [];
        $ambiguousCompanies = [];

        foreach ($groups as $group) {
            [$company, $requiresReview] = $this->selectCandidate($group);
            if ($requiresReview) {
                $ambiguousCompanies[] = [
                    'nit' => NitNormalizer::normalize((string) $group->first()->nit),
                    'companies' => $group->sortBy('id_empresa')
                        ->values()
                        ->map(static fn (Empresa $candidate): array => [
                            'id_empresa' => (int) $candidate->id_empresa,
                            'nombre_empresa' => $candidate->nombre_empresa,
                            'nit' => $candidate->nit,
                            'fecha_actualizacion_empresa' => $candidate->fecha_actualizacion_empresa,
                            'fecha_creacion' => $candidate->fecha_creacion,
                        ])
                        ->all(),
                ];

                continue;
            }

            $canonical[] = [
                'id_empresa' => (int) $company->id_empresa,
                'nombre_empresa' => $company->nombre_empresa,
                'nombre_responsable' => $company->nombre_responsable,
                'nombre_gerente' => $company->nombre_gerente,
                'nit' => $company->nit,
            ];
        }

        usort(
            $canonical,
            static fn (array $left, array $right): int => strcasecmp(
                (string) $left['nombre_empresa'],
                (string) $right['nombre_empresa']
            ) ?: ($left['id_empresa'] <=> $right['id_empresa'])
        );

        return [
            'companies' => $canonical,
            'ambiguous_count' => count($ambiguousCompanies),
            'ambiguous_companies' => $ambiguousCompanies,
        ];
    }

    /**
     * Re-resolves an active company ID to the canonical active record for its NIT.
     */
    public function canonicalReservationCompanyId(int $companyId): int
    {
        $company = Empresa::query()
            ->where('es_activo', 1)
            ->lockForUpdate()
            ->find($companyId, ['id_empresa', 'nit']);

        if (! $company) {
            throw ValidationException::withMessages([
                'id_empresa' => 'La empresa seleccionada no existe o está inactiva. Actualice la lista.',
            ]);
        }

        $nit = NitNormalizer::normalize((string) $company->nit);
        if (strlen($nit) < 5) {
            return (int) $company->id_empresa;
        }

        [$candidate, $requiresReview] = $this->selectCandidate(
            $this->companiesForNit($nit, lockForUpdate: true, activeOnly: true)
        );

        if ($requiresReview || $candidate === null) {
            throw ValidationException::withMessages([
                'id_empresa' => 'Las fichas con el NIT de esta empresa requieren revisión antes de reservar.',
            ]);
        }

        return (int) $candidate->id_empresa;
    }

    /**
     * Resolves a typed company name only when it identifies one active company
     * or duplicate records that share one valid NIT.
     */
    public function reservationCompanyIdByName(string $name): ?int
    {
        $matches = Empresa::query()
            ->where('nombre_empresa', $name)
            ->get(['id_empresa', 'nit', 'es_activo']);

        if ($matches->isEmpty()) {
            return null;
        }

        $active = $matches->filter(
            static fn (Empresa $company): bool => (int) $company->es_activo === 1
        )->values();

        if ($active->isEmpty()) {
            throw ValidationException::withMessages([
                'empresa' => 'La empresa con ese nombre está inactiva. Seleccione una empresa activa o solicite su revisión.',
            ]);
        }

        if ($active->count() === 1) {
            return $this->canonicalReservationCompanyId((int) $active->first()->id_empresa);
        }

        $normalizedNits = $active
            ->map(static fn (Empresa $company): string => NitNormalizer::normalize((string) $company->nit))
            ->unique()
            ->values();

        if ($normalizedNits->count() !== 1 || strlen((string) $normalizedNits->first()) < 5) {
            throw ValidationException::withMessages([
                'empresa' => 'Hay varias empresas con ese nombre. Seleccione una de la lista para usar su ficha correcta.',
            ]);
        }

        return $this->canonicalReservationCompanyId((int) $active->first()->id_empresa);
    }

    /**
     * Resolves the selected NIT and switches only the contract being filled.
     * The caller must already be inside a database transaction.
     *
     * @return array{empresa: Empresa, empresa_anterior_id: ?int}
     */
    public function resolveForContract(Contrato $contract, Request $request): array
    {
        $currentCompanyId = (int) $contract->id_empresa;
        $nit = NitNormalizer::normalize((string) $request->input('nit', ''));
        $selectedCompanyId = $request->input('id_empresa_destino');

        [$candidate, $requiresReview] = $this->selectCandidate(
            $this->companiesForNitPreferringActive($nit, lockForUpdate: true)
        );

        if ($candidate === null) {
            if ($selectedCompanyId !== null) {
                throw ValidationException::withMessages([
                    'nit' => 'La empresa seleccionada ya no coincide con el NIT ingresado. Vuelva a buscarla.',
                ]);
            }

            return [
                'empresa' => Empresa::query()->lockForUpdate()->findOrFail($currentCompanyId),
                'empresa_anterior_id' => null,
            ];
        }

        if ($requiresReview) {
            throw ValidationException::withMessages([
                'nit' => 'Hay varias empresas con este NIT y no se pudo determinar una única ficha principal. Solicite revisión antes de continuar.',
            ]);
        }

        $candidateId = (int) $candidate->id_empresa;
        $sameCompanyAlreadyLinked = $candidateId === $currentCompanyId
            && ($selectedCompanyId === null || (int) $selectedCompanyId === $candidateId);
        if (! $sameCompanyAlreadyLinked && (int) $selectedCompanyId !== $candidateId) {
            throw ValidationException::withMessages([
                'nit' => 'Este NIT ya está registrado. Confirme el uso de la empresa encontrada antes de guardar.',
            ]);
        }

        $company = Empresa::query()->findOrFail($candidateId);
        if (NitNormalizer::normalize((string) $company->nit) !== $nit) {
            throw ValidationException::withMessages([
                'nit' => 'El NIT de la empresa seleccionada cambió. Vuelva a buscarla.',
            ]);
        }

        if ($candidateId === $currentCompanyId) {
            return [
                'empresa' => $company,
                'empresa_anterior_id' => null,
            ];
        }

        $contract->id_empresa = $candidateId;
        $contract->save();

        return [
            'empresa' => $company,
            'empresa_anterior_id' => $currentCompanyId,
        ];
    }

    public function existsByNit(string $nit): bool
    {
        $normalizedNit = NitNormalizer::normalize($nit);
        return $normalizedNit !== '' && Empresa::query()
            ->whereRaw(self::NORMALIZED_NIT_SQL.' = ?', [$normalizedNit])
            ->exists();
    }

    public function deleteProvisionalIfOrphaned(?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        $company = Empresa::query()->lockForUpdate()->find($companyId);
        if (! $company
            || $company->pendiente_llenado !== self::PROVISIONAL_MARKER
            || trim((string) $company->nit) !== '') {
            return false;
        }

        foreach (self::COMPANY_REFERENCE_TABLES as $table) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, 'id_empresa')
                && DB::table($table)->where('id_empresa', $companyId)->exists()) {
                return false;
            }
        }

        return (bool) $company->delete();
    }

    /**
     * @param Collection<int, Empresa> $companies
     * @return array{0: Empresa|null, 1: bool}
     */
    private function selectCandidate(Collection $companies): array
    {
        if ($companies->isEmpty()) {
            return [null, false];
        }

        $ranked = $companies->map(fn (Empresa $company): array => [
            'company' => $company,
            'updated' => $this->dateRank($company->fecha_actualizacion_empresa),
            'created' => $this->dateRank($company->fecha_creacion),
        ])->map(function (array $candidate): array {
            $updated = $candidate['updated'];
            $created = $candidate['created'];

            if ($updated !== null && $created !== null && $updated < $created) {
                $updated = null;
            }

            $candidate['effective'] = $updated ?? $created;

            return $candidate;
        })->sort(function (array $left, array $right): int {
            return $right['effective'] <=> $left['effective'];
        })->values();

        $first = $ranked->first();
        $second = $ranked->get(1);
        $requiresReview = $second !== null
            && $first['effective'] === $second['effective'];

        return [$first['company'], $requiresReview];
    }

    private function companiesForNit(
        string $normalizedNit,
        bool $lockForUpdate = false,
        bool $activeOnly = false
    ): Collection
    {
        $query = Empresa::query()
            ->whereRaw(self::NORMALIZED_NIT_SQL.' = ?', [$normalizedNit])
            ->select(self::CANDIDATE_FIELDS)
            ->orderBy('id_empresa');

        if ($activeOnly) {
            $query->where('es_activo', 1);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function companiesForNitPreferringActive(string $normalizedNit, bool $lockForUpdate = false): Collection
    {
        $activeCompanies = $this->companiesForNit(
            $normalizedNit,
            lockForUpdate: $lockForUpdate,
            activeOnly: true
        );

        return $activeCompanies->isNotEmpty()
            ? $activeCompanies
            : $this->companiesForNit($normalizedNit, lockForUpdate: $lockForUpdate);
    }

    private function dateRank(mixed $value): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false || (int) date('Y', $timestamp) < 1901 || $timestamp > time() + 86400) {
            return null;
        }

        return $timestamp;
    }

    private function serializeCompany(Empresa $company): array
    {
        return collect(self::SERIALIZED_COMPANY_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $company->{$field}])
            ->all();
    }
}
