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

        [$company, $requiresReview] = $this->selectCandidate($this->companiesForNit($normalizedNit));
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
            $this->companiesForNit($nit, lockForUpdate: true)
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
        ])->sort(function (array $left, array $right): int {
            return ($right['updated'] <=> $left['updated'])
                ?: ($right['created'] <=> $left['created']);
        })->values();

        $first = $ranked->first();
        $second = $ranked->get(1);
        $requiresReview = $second !== null
            && $first['updated'] === $second['updated']
            && $first['created'] === $second['created'];

        return [$first['company'], $requiresReview];
    }

    private function companiesForNit(string $normalizedNit, bool $lockForUpdate = false): Collection
    {
        $query = Empresa::query()
            ->whereRaw(self::NORMALIZED_NIT_SQL.' = ?', [$normalizedNit])
            ->select(self::CANDIDATE_FIELDS)
            ->orderBy('id_empresa');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
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
