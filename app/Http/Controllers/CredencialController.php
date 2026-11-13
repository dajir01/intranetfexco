<?php

namespace App\Http\Controllers;

use App\Exports\AcreditadosExport;
use App\Exports\EmpresasCredencialesExport;
use App\Jobs\EnviarCredencialDigitalJob;
use App\Mail\Credenciales\AccesosEmpresaMail;
use App\Models\Contrato;
use App\Models\CredencialTipoDiseño;
use App\Models\Empresa;
use App\Models\EmpresaEventoCredencial;
use App\Models\Feria;
use App\Models\Pabellon;
use App\Models\Stand;
use App\Services\CredentialPhotoStorage;
use App\Support\CredentialModality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CredencialController extends Controller
{
    public function eventos(): JsonResponse
    {
        $eventos = Feria::query()
            ->select('id_feria', 'nombre_feria', 'fecha_inicio', 'fecha_fin', 'tipo_credenciales', 'acreditacion_empresa_habilitada')
            ->whereNotNull('tipo_credenciales')
            ->where('tipo_credenciales', '!=', '')
            ->orderByFechaEvento()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $eventos,
        ]);
    }

    public function actualizarHabilitacionAcreditacion(Request $request, int $idFeria): JsonResponse
    {
        $validated = $request->validate([
            'habilitada' => ['required', 'boolean'],
        ]);

        $feria = Feria::query()->findOrFail($idFeria);
        $feria->acreditacion_empresa_habilitada = (bool) $validated['habilitada'];
        $feria->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id_feria' => (int) $feria->id_feria,
                'acreditacion_empresa_habilitada' => (bool) $feria->acreditacion_empresa_habilitada,
            ],
        ]);
    }

    public function mostrarFotoAcreditado(string $filename): BinaryFileResponse
    {
        abort_unless(preg_match('/^acreditado_[a-f0-9-]+\.(jpg|jpeg|png)$/i', $filename), 404);

        $path = Storage::disk('credential_photos')->path($filename);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function empresas(Request $request, int $idFeria): JsonResponse
    {
        $feria = Feria::query()
            ->select('id_feria', 'nombre_feria', 'tipo_credenciales')
            ->findOrFail($idFeria);

        $tiposHabilitados = $this->tiposCredenciales($feria->tipo_credenciales);

        if ($tiposHabilitados->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'evento' => $feria,
                    'total' => 0,
                ],
            ]);
        }

        $adicionalesPorContrato = DB::table('cred_adicionales')
            ->where('feria', $idFeria)
            ->select(
                'contrato',
                DB::raw('COALESCE(SUM(CASE WHEN tipo_movimiento = "adicion" THEN cant_adic ELSE -cant_adic END), 0) as total')
            )
            ->whereNotNull('contrato')
            ->groupBy('contrato')
            ->pluck('total', 'contrato');

        $empresas = DB::table('contrato as contratos')
            ->join('empresas', 'empresas.id_empresa', '=', 'contratos.id_empresa')
            ->join('eventos', 'eventos.id_feria', '=', 'contratos.id_feria')
            ->leftJoin('empresa_evento_credenciales as vinculaciones', function ($join) use ($tiposHabilitados) {
                $join->on('vinculaciones.id_empresa', '=', 'contratos.id_empresa')
                    ->on('vinculaciones.id_feria', '=', 'contratos.id_feria')
                    ->on('vinculaciones.id_contrato', '=', 'contratos.id_contrato')
                    ->whereIn('vinculaciones.tipo_credencial', $tiposHabilitados->all())
                    ->where('vinculaciones.estado', true);
            })
            ->where('contratos.id_feria', $idFeria)
            ->where('contratos.codigo_contrato', '>', 0)
            ->where('contratos.total_stands', '>', 0)
            ->select(
                'contratos.id_contrato',
                'vinculaciones.id as id_vinculacion',
                'vinculaciones.tipo_credencial as tipo_credencial',
                'empresas.id_empresa',
                'empresas.nombre_empresa',
                'contratos.codigo_contrato',
                'contratos.tipo_credenciales',
                'eventos.codigo_contrato as codigo_evento',
                DB::raw('COALESCE(vinculaciones.cantidad_credenciales, contratos.nro_credenciales) as credenciales_asignadas')
            )
            ->groupBy(
                'contratos.id_contrato',
                'vinculaciones.id',
                'vinculaciones.tipo_credencial',
                'empresas.id_empresa',
                'empresas.nombre_empresa',
                'contratos.codigo_contrato',
                'eventos.codigo_contrato',
                'contratos.tipo_credenciales',
                'contratos.nro_credenciales',
                'vinculaciones.cantidad_credenciales'
            )
            ->orderBy('empresas.nombre_empresa')
            ->orderBy('contratos.codigo_contrato')
            ->get()
            ->map(function ($empresa) use ($idFeria, $tiposHabilitados, $adicionalesPorContrato) {
                $adicionales = (int) ($adicionalesPorContrato->get($empresa->id_contrato) ?? 0);
                $asignadas = (int) $empresa->credenciales_asignadas + $adicionales;
                $tipo = $empresa->tipo_credencial ?? $tiposHabilitados->first();
                $utilizadas = $this->credencialesUtilizadas(
                    $idFeria,
                    (int) $empresa->id_empresa,
                    (int) $empresa->id_contrato,
                    $empresa->id_vinculacion ? (int) $empresa->id_vinculacion : null,
                    $tipo,
                );

                return [
                    'id_contrato' => (int) $empresa->id_contrato,
                    'id_vinculacion' => $empresa->id_vinculacion ? (int) $empresa->id_vinculacion : null,
                    'id_empresa' => (int) $empresa->id_empresa,
                    'nombre_empresa' => $empresa->nombre_empresa,
                    'tipo_credencial' => $tipo,
                    'codigo_contrato' => (string) $empresa->codigo_evento.str_pad((string) $empresa->codigo_contrato, 5, '0', STR_PAD_LEFT),
                    'modalidad_credencial' => CredentialModality::label($idFeria, $empresa->tipo_credenciales === null ? null : (int) $empresa->tipo_credenciales),
                    'credenciales_asignadas' => $asignadas,
                    'credenciales_utilizadas' => $utilizadas,
                ];
            })
            ->values();

        $vinculacionesSinContratoObjs = EmpresaEventoCredencial::query()
            ->with('empresa:id_empresa,nombre_empresa')
            ->where('id_feria', $idFeria)
            ->whereIn('tipo_credencial', $tiposHabilitados->all())
            ->where('estado', true)
            ->whereNull('id_contrato')
            ->get();

        $adicionalesPorVinculacion = DB::table('cred_adicionales')
            ->where('feria', $idFeria)
            ->select(
                'id_empresa_evento_credencial',
                DB::raw('COALESCE(SUM(CASE WHEN tipo_movimiento = "adicion" THEN cant_adic ELSE 0 END), 0) as total')
            )
            ->whereNotNull('id_empresa_evento_credencial')
            ->groupBy('id_empresa_evento_credencial')
            ->pluck('total', 'id_empresa_evento_credencial');

        $vinculacionesSinContrato = $vinculacionesSinContratoObjs
            ->map(function ($vinculacion) use ($adicionalesPorVinculacion) {
                $adicionales = (int) ($adicionalesPorVinculacion->get($vinculacion->id) ?? 0);
                $credencialesUtilizadas = $this->credencialesUtilizadas(
                    (int) $vinculacion->id_feria,
                    (int) $vinculacion->id_empresa,
                    null,
                    (int) $vinculacion->id,
                    $vinculacion->tipo_credencial,
                );

                return [
                    'id_contrato' => null,
                    'id_vinculacion' => (int) $vinculacion->id,
                    'id_empresa' => (int) $vinculacion->id_empresa,
                    'nombre_empresa' => $vinculacion->empresa?->nombre_empresa,
                    'tipo_credencial' => $vinculacion->tipo_credencial,
                    'modalidad_credencial' => CredentialModality::label((int) $vinculacion->id_feria, $vinculacion->tipo_credenciales === null ? null : (int) $vinculacion->tipo_credenciales),
                    'codigo_contrato' => 'Sin contrato',
                    'credenciales_asignadas' => (int) $vinculacion->cantidad_credenciales + $adicionales,
                    'credenciales_utilizadas' => $credencialesUtilizadas,
                ];
            });

        $empresas = $empresas->concat($vinculacionesSinContrato)->values();

        $contratosIds = $empresas->pluck('id_contrato')->filter()->unique()->values();
        $contratosUbicacion = Contrato::query()
            ->select(
                'id_contrato',
                'stand_1', 'stand_2', 'stand_3', 'stand_4', 'stand_5', 'stand_6',
                'stand_7', 'stand_8', 'stand_9', 'stand_10', 'stand_11', 'stand_12'
            )
            ->whereIn('id_contrato', $contratosIds)
            ->get();

        $standIds = $contratosUbicacion
            ->flatMap(fn ($contrato) => collect(range(1, 12))->map(fn ($index) => $contrato->{"stand_$index"}))
            ->filter()
            ->unique()
            ->values();
        $stands = Stand::query()
            ->select('id_stand', 'numero_stand', 'area_stand', 'id_pabellon')
            ->whereIn('id_stand', $standIds)
            ->get()
            ->keyBy('id_stand');
        $pabellones = Pabellon::query()
            ->select('id_pabellon', 'nombre_pabellon')
            ->whereIn('id_pabellon', $stands->pluck('id_pabellon')->filter()->unique())
            ->get()
            ->keyBy('id_pabellon');

        $ubicacionesContrato = $contratosUbicacion->mapWithKeys(function ($contrato) use ($stands, $pabellones) {
            $standsContrato = collect(range(1, 12))
                ->map(fn ($index) => $stands->get($contrato->{"stand_$index"}))
                ->filter()
                ->values();
            $primerStand = $standsContrato->first();

            return [$contrato->id_contrato => [
                'pabellon' => $primerStand ? ($pabellones->get($primerStand->id_pabellon)?->nombre_pabellon ?? 'N/A') : 'N/A',
                'stands' => $standsContrato->pluck('numero_stand')->values()->all(),
            ]];
        });

        $empresas = $empresas
            ->groupBy('id_empresa')
            ->map(function ($contratosEmpresa) use ($ubicacionesContrato) {
                $primerRegistro = $contratosEmpresa->first();
                $contratos = $contratosEmpresa
                    ->map(fn ($empresa) => [
                        'id_contrato' => $empresa['id_contrato'],
                        'id_vinculacion' => $empresa['id_vinculacion'],
                        'codigo_contrato' => $empresa['codigo_contrato'],
                        'modalidad_credencial' => $empresa['modalidad_credencial'] ?? null,
                        'tipo_credencial' => $empresa['tipo_credencial'],
                        'pabellon' => $ubicacionesContrato->get($empresa['id_contrato'])['pabellon'] ?? null,
                        'stands' => $ubicacionesContrato->get($empresa['id_contrato'])['stands'] ?? [],
                        'credenciales_asignadas' => $empresa['credenciales_asignadas'],
                        'credenciales_utilizadas' => $empresa['credenciales_utilizadas'],
                    ])
                    ->values()
                    ->all();

                return [
                    ...$primerRegistro,
                    'id_contrato' => $contratos[0]['id_contrato'] ?? null,
                    'id_vinculacion' => $primerRegistro['id_vinculacion'],
                    'codigo_contrato' => $contratos[0]['codigo_contrato'] ?? 'Sin contrato',
                    'modalidad_credencial' => $contratos[0]['modalidad_credencial'] ?? null,
                    'tipo_credencial' => $contratos[0]['tipo_credencial'] ?? $primerRegistro['tipo_credencial'],
                    'credenciales_asignadas' => $contratosEmpresa->sum('credenciales_asignadas'),
                    'credenciales_utilizadas' => $contratosEmpresa->sum('credenciales_utilizadas'),
                    'contratos' => $contratos,
                ];
            })
            ->values();

        $enviosWhatsapp = DB::table('credencial_empresa_envios')
            ->where('id_feria', $idFeria)
            ->where('canal', 'whatsapp')
            ->select(
                'id_empresa',
                DB::raw('COUNT(*) as total'),
            )
            ->groupBy('id_empresa')
            ->get()
            ->keyBy('id_empresa');

        $enviosEmail = DB::table('credencial_empresa_envios')
            ->where('id_feria', $idFeria)
            ->where('canal', 'email')
            ->select(
                'id_empresa',
                DB::raw('SUM(CASE WHEN accion = "enviado" THEN 1 ELSE 0 END) as total'),
            )
            ->groupBy('id_empresa')
            ->get()
            ->keyBy('id_empresa');

        $puedeVerHistorialWhatsapp = Auth::user()?->hasPermission('credenciales.empresas.whatsapp.ver') ?? false;
        $puedeVerHistorialEmail = Auth::user()?->hasPermission('credenciales.empresas.email.ver') ?? false;
        $empresas = $empresas->map(function (array $empresa) use ($enviosWhatsapp, $enviosEmail, $puedeVerHistorialWhatsapp, $puedeVerHistorialEmail) {
            $envios = $enviosWhatsapp->get($empresa['id_empresa']);
            $enviosCorreo = $enviosEmail->get($empresa['id_empresa']);

            if ($puedeVerHistorialWhatsapp) {
                $empresa = [
                    ...$empresa,
                    'whatsappEnvios' => (int) ($envios->total ?? 0),
                ];
            }

            if ($puedeVerHistorialEmail) {
                $empresa = [
                    ...$empresa,
                    'emailEnvios' => (int) ($enviosCorreo->total ?? 0),
                ];
            }

            return $empresa;
        });

        return response()->json([
            'success' => true,
            'data' => $empresas,
            'meta' => [
                'evento' => $feria,
                'total' => $empresas->count(),
            ],
        ]);
    }

    public function acreditadosPorEvento(Request $request, int $idFeria): JsonResponse
    {
        $feria = Feria::query()->findOrFail($idFeria);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'modalidad' => ['nullable', 'string', 'in:Digital,Física'],
            'tipo_credencial' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = DB::table('credenciales as acreditados')
            ->join('empresas', 'empresas.id_empresa', '=', 'acreditados.id_empresa')
            ->join('expositores', 'expositores.id_exp', '=', 'acreditados.id_expositor')
            ->where('acreditados.id_feria', $feria->id_feria)
            ->where('acreditados.estado_credencial', '!=', 3);

        $termino = trim((string) ($validated['search'] ?? ''));
        if ($termino !== '') {
            $like = '%'.$termino.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('empresas.nombre_empresa', 'like', $like)
                    ->orWhere('expositores.nombre', 'like', $like)
                    ->orWhere('expositores.ci', 'like', $like);
            });
        }

        if (($validated['modalidad'] ?? null) === 'Física' && ! CredentialModality::isDigitalOnlyEvent((int) $feria->id_feria)) {
            $query->where('acreditados.modalidad_credencial', 0);
        } elseif (($validated['modalidad'] ?? null) === 'Física') {
            $query->whereRaw('1 = 0');
        } elseif (($validated['modalidad'] ?? null) === 'Digital' && ! CredentialModality::isDigitalOnlyEvent((int) $feria->id_feria)) {
            $query->where(function ($sub) {
                $sub->whereNull('acreditados.modalidad_credencial')->orWhere('acreditados.modalidad_credencial', 1);
            });
        }

        $tipoCodigo = match ($validated['tipo_credencial'] ?? null) {
            'Expositor' => 1, 'Prensa' => 2, 'Oficial' => 3, 'Servicios' => 4, 'Negocios' => 5,
            default => null,
        };
        if ($tipoCodigo !== null) {
            $query->where('acreditados.tipo_credencial', $tipoCodigo);
        }

        $paginado = $query
            ->select(
                'acreditados.id_credencial',
                'acreditados.id_empresa',
                'acreditados.id_expositor',
                'acreditados.modalidad_credencial',
                'acreditados.tipo_credencial',
                'acreditados.estado_credencial',
                'empresas.nombre_empresa',
                'empresas.nit',
                'expositores.nombre',
                'expositores.ci',
                'expositores.exp',
                'expositores.celular',
                'expositores.email',
                'expositores.com_email',
                'expositores.tipo_documento',
                'expositores.foto'
            )
            ->orderBy('empresas.nombre_empresa')
            ->orderBy('expositores.nombre')
            ->paginate((int) ($validated['per_page'] ?? 10), ['*'], 'page', (int) ($validated['page'] ?? 1));

        $acreditados = collect($paginado->items());

        $salidas = Schema::hasTable('credencial_digital_descargas')
            ? DB::table('credencial_digital_descargas')
                ->whereIn('id_credencial', $acreditados->pluck('id_credencial'))
                ->selectRaw('id_credencial, COUNT(*) as cantidad, MAX(created_at) as ultima_fecha, MAX(tipo_descarga) as tipo')
                ->groupBy('id_credencial')
                ->get()
                ->keyBy('id_credencial')
            : collect();

        $acreditados = $acreditados->map(fn ($acreditado) => [
            'id_credencial' => (int) $acreditado->id_credencial,
            'id_empresa' => (int) $acreditado->id_empresa,
            'id_expositor' => (int) $acreditado->id_expositor,
            'nombre_empresa' => $acreditado->nombre_empresa,
            'nit' => $acreditado->nit,
            'nombre' => $acreditado->nombre,
            'ci' => $acreditado->ci,
            'exp' => $acreditado->exp,
            'celular' => $acreditado->celular,
            'email' => $acreditado->email ?: $acreditado->com_email,
            'tipo_documento' => $acreditado->tipo_documento,
            'foto' => $acreditado->foto,
            'tipo_credencial' => match ((int) $acreditado->tipo_credencial) {
                1 => 'Expositor', 2 => 'Prensa', 3 => 'Oficial', 4 => 'Servicios', 5 => 'Negocios',
                default => 'Sin tipo definido',
            },
            'modalidad_credencial' => CredentialModality::label((int) $feria->id_feria, $acreditado->modalidad_credencial === null ? null : (int) $acreditado->modalidad_credencial, true),
            'estado_credencial_codigo' => (int) $acreditado->estado_credencial,
            'estado_credencial' => $this->estadoCredencialLabel((int) $acreditado->estado_credencial),
            'salida' => $salidas->get($acreditado->id_credencial),
        ])
            ->values();

        $conteosPorTipo = DB::table('credenciales')
            ->where('id_feria', $feria->id_feria)
            ->where('estado_credencial', '!=', 3)
            ->select(
                'tipo_credencial',
                DB::raw('COUNT(*) as acreditadas'),
                DB::raw('SUM(CASE WHEN id_feria = '.CredentialModality::DIGITAL_ONLY_EVENT_ID.' OR modalidad_credencial = 1 THEN 1 ELSE 0 END) as digitales'),
                DB::raw('SUM(CASE WHEN id_feria != '.CredentialModality::DIGITAL_ONLY_EVENT_ID.' AND modalidad_credencial = 0 THEN 1 ELSE 0 END) as fisicas'),
                DB::raw('SUM(CASE WHEN id_feria != '.CredentialModality::DIGITAL_ONLY_EVENT_ID.' AND modalidad_credencial IS NULL THEN 1 ELSE 0 END) as digitales_legacy')
            )
            ->groupBy('tipo_credencial')
            ->get()
            ->keyBy(fn ($registro) => $this->tipoCredencialNombre((int) $registro->tipo_credencial));
        $resumenPorTipo = $this->tiposCredenciales($feria->tipo_credenciales)
            ->map(fn (string $tipo) => [
                'tipo' => $tipo,
                'acreditadas' => (int) ($conteosPorTipo->get($tipo)?->acreditadas ?? 0),
                'digitales' => (int) ($conteosPorTipo->get($tipo)?->digitales ?? 0) + (int) ($conteosPorTipo->get($tipo)?->digitales_legacy ?? 0),
                'fisicas' => (int) ($conteosPorTipo->get($tipo)?->fisicas ?? 0),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $acreditados,
            'meta' => [
                'evento' => $feria,
                'total' => $paginado->total(),
                'current_page' => $paginado->currentPage(),
                'per_page' => $paginado->perPage(),
                'last_page' => $paginado->lastPage(),
                'resumen_por_tipo' => $resumenPorTipo,
            ],
        ]);
    }

    public function exportarAcreditados(Request $request, int $idFeria): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $feria = Feria::query()->findOrFail($idFeria);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'modalidad' => ['nullable', 'string', 'in:Digital,Física'],
            'tipo_credencial' => ['nullable', 'string', 'max:50'],
        ]);

        $query = DB::table('credenciales as acreditados')
            ->join('empresas', 'empresas.id_empresa', '=', 'acreditados.id_empresa')
            ->join('expositores', 'expositores.id_exp', '=', 'acreditados.id_expositor')
            ->where('acreditados.id_feria', $feria->id_feria)
            ->where('acreditados.estado_credencial', '!=', 3);

        $termino = trim((string) ($validated['search'] ?? ''));
        if ($termino !== '') {
            $like = '%'.$termino.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('empresas.nombre_empresa', 'like', $like)
                    ->orWhere('expositores.nombre', 'like', $like)
                    ->orWhere('expositores.ci', 'like', $like);
            });
        }

        if (($validated['modalidad'] ?? null) === 'Física' && ! CredentialModality::isDigitalOnlyEvent((int) $feria->id_feria)) {
            $query->where('acreditados.modalidad_credencial', 0);
        } elseif (($validated['modalidad'] ?? null) === 'Física') {
            $query->whereRaw('1 = 0');
        } elseif (($validated['modalidad'] ?? null) === 'Digital' && ! CredentialModality::isDigitalOnlyEvent((int) $feria->id_feria)) {
            $query->where(function ($sub) {
                $sub->whereNull('acreditados.modalidad_credencial')->orWhere('acreditados.modalidad_credencial', 1);
            });
        }

        $tipoCodigo = match ($validated['tipo_credencial'] ?? null) {
            'Expositor' => 1, 'Prensa' => 2, 'Oficial' => 3, 'Servicios' => 4, 'Negocios' => 5,
            default => null,
        };
        if ($tipoCodigo !== null) {
            $query->where('acreditados.tipo_credencial', $tipoCodigo);
        }

        $query->select(
            'empresas.nombre_empresa',
            'expositores.nombre',
            'expositores.ci',
            'expositores.exp',
            'expositores.celular',
            'expositores.email',
            'expositores.com_email',
            'acreditados.tipo_credencial',
            'acreditados.estado_credencial'
        )->orderBy('empresas.nombre_empresa')->orderBy('expositores.nombre');

        $nombreFeria = Str::slug($feria->nombre_feria ?: 'feria');

        $fechaExportacion = now()->format('d/m/Y H:i');

        return Excel::download(
            new AcreditadosExport($query, $feria->nombre_feria, $fechaExportacion),
            "acreditados-{$nombreFeria}-".now()->format('Ymd_His').'.xlsx'
        );
    }

    public function exportarEmpresasCredenciales(Request $request, int $idFeria): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $feria = Feria::query()->select('id_feria', 'nombre_feria')->findOrFail($idFeria);
        $validated = $request->validate([
            'tipo_credencial' => ['nullable', 'string', 'max:50'],
        ]);
        $datos = $this->empresas($request, $idFeria)->getData(true)['data'];
        $movimientos = DB::table('cred_adicionales')
            ->where('feria', $idFeria)
            ->get(['contrato', 'id_empresa_evento_credencial', 'cant_adic', 'tipo_movimiento', 'obs']);

        $registros = collect($datos)
            ->flatMap(fn (array $empresa) => collect($empresa['contratos'] ?? [])->map(fn (array $registro) => [
                ...$registro,
                'id_empresa' => $empresa['id_empresa'],
                'nombre_empresa' => $empresa['nombre_empresa'],
            ]))
            ->filter(fn (array $registro) => empty($validated['tipo_credencial']) || $registro['tipo_credencial'] === $validated['tipo_credencial'])
            ->groupBy(fn (array $registro) => $registro['id_empresa'].'|'.$registro['tipo_credencial'])
            ->map(function ($registrosTipo) use ($movimientos) {
                $adicionales = 0;
                $disminuciones = 0;
                $motivosAdicionales = [];
                $motivosDisminuciones = [];

                foreach ($registrosTipo as $registro) {
                    $movimientosRegistro = $movimientos->filter(fn ($movimiento) => $registro['id_contrato']
                        ? (int) $movimiento->contrato === (int) $registro['id_contrato']
                        : (int) $movimiento->id_empresa_evento_credencial === (int) $registro['id_vinculacion']);
                    foreach ($movimientosRegistro as $movimiento) {
                        if ($movimiento->tipo_movimiento === 'disminucion') {
                            $disminuciones += (int) $movimiento->cant_adic;
                            if ($movimiento->obs) {
                                $motivosDisminuciones[] = $movimiento->obs;
                            }
                        } else {
                            $adicionales += (int) $movimiento->cant_adic;
                            if ($movimiento->obs) {
                                $motivosAdicionales[] = $movimiento->obs;
                            }
                        }
                    }
                }

                $total = $registrosTipo->sum('credenciales_asignadas');
                $usadas = $registrosTipo->sum('credenciales_utilizadas');

                return [
                    $registrosTipo->first()['nombre_empresa'],
                    $registrosTipo->first()['tipo_credencial'],
                    max(0, $total - $adicionales + $disminuciones),
                    $usadas,
                    $adicionales,
                    implode(' | ', array_unique($motivosAdicionales)) ?: '—',
                    $disminuciones,
                    implode(' | ', array_unique($motivosDisminuciones)) ?: '—',
                    $total,
                ];
            })
            ->sortBy(fn (array $registro) => $registro[0].'|'.$registro[1])
            ->values();

        return Excel::download(
            new EmpresasCredencialesExport($registros, $feria->nombre_feria, now()->format('d/m/Y H:i')),
            'empresas-credenciales-'.Str::slug($feria->nombre_feria ?: 'feria').'-'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function iniciarEnvioWhatsappEmpresa(int $idFeria, int $idEmpresa): JsonResponse
    {
        $datos = $this->datosAccesoWhatsappEmpresa($idFeria, $idEmpresa);
        $envioId = $this->registrarAccionEnvioEmpresa($idFeria, $idEmpresa, 'whatsapp', 'abierto', $datos['telefono']);

        Log::info('Clic para abrir WhatsApp registrado para compartir accesos de empresa.', [
            'id_envio' => $envioId,
            'id_feria' => $idFeria,
            'id_empresa' => $idEmpresa,
            'id_usuario' => Auth::user()?->id_usuario,
            'campo_telefono' => 'telefono_responsable',
            'destinatario_ultimos_digitos' => substr($datos['telefono'], -4),
            'cantidad_credenciales' => $datos['cantidad_credenciales'],
            'accion' => 'abierto',
            'nota' => 'El registro confirma el clic; no confirma que el mensaje se haya enviado en WhatsApp.',
        ]);

        return response()->json([
            'success' => true,
            'data' => ['id_envio' => $envioId, ...$datos],
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    }

    public function prepararCopiaWhatsappEmpresa(int $idFeria, int $idEmpresa): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->datosAccesoWhatsappEmpresa($idFeria, $idEmpresa),
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    }

    public function registrarCopiaWhatsappEmpresa(int $idFeria, int $idEmpresa): JsonResponse
    {
        $datos = $this->datosAccesoWhatsappEmpresa($idFeria, $idEmpresa);
        $envioId = $this->registrarAccionEnvioEmpresa($idFeria, $idEmpresa, 'whatsapp', 'copiado', $datos['telefono']);

        Log::info('Mensaje de accesos de empresa copiado para WhatsApp.', [
            'id_envio' => $envioId,
            'id_feria' => $idFeria,
            'id_empresa' => $idEmpresa,
            'id_usuario' => Auth::user()?->id_usuario,
            'campo_telefono' => 'telefono_responsable',
            'destinatario_ultimos_digitos' => substr($datos['telefono'], -4),
            'cantidad_credenciales' => $datos['cantidad_credenciales'],
            'accion' => 'copiado',
        ]);

        return response()->json([
            'success' => true,
            'data' => ['id_envio' => $envioId],
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    }

    public function enviarAccesosEmailEmpresa(int $idFeria, int $idEmpresa): JsonResponse
    {
        $mailer = (string) config('mail.default');
        if ($this->usaTransporteCorreoNoEntregable($mailer)) {
            Log::critical('Envío de accesos por correo bloqueado: el transporte configurado puede escribir el mensaje en logs o no entregarlo.', [
                'mailer' => $mailer,
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::user()?->id_usuario,
                'etapa' => 'validacion_transporte',
            ]);

            abort(503, 'El correo está configurado con un transporte de prueba que no entrega mensajes de forma segura. Configure un servicio de correo real e inténtelo nuevamente.');
        }

        $datos = $this->datosCredencialesEmpresa($idFeria, $idEmpresa);
        $email = trim((string) $datos['email']);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('No se pudo enviar correo de accesos: el contacto de la empresa no tiene un correo válido.', [
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::user()?->id_usuario,
                'campo_email_consultado' => 'email_representante',
                'etapa' => 'validacion_destinatario',
            ]);

            abort(422, 'La persona de contacto no tiene un correo electrónico válido registrado.');
        }

        $envioId = $this->registrarAccionEnvioEmpresa($idFeria, $idEmpresa, 'email', 'procesando', $email);
        $datosCorreo = $datos;
        unset($datosCorreo['email'], $datosCorreo['telefono']);

        try {
            Mail::to($email)->send(new AccesosEmpresaMail($datosCorreo));
        } catch (\Throwable $exception) {
            Log::error('Error al enviar por correo los accesos de una empresa.', [
                'id_envio' => $envioId,
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::user()?->id_usuario,
                'cantidad_credenciales' => $datos['cantidad_credenciales'],
                'excepcion' => $exception::class,
                'codigo_error' => (int) $exception->getCode(),
                'etapa' => 'transporte_correo',
            ]);

            try {
                $this->actualizarAccionEnvioEmpresa($envioId, 'error');
            } catch (\Throwable $estadoException) {
                Log::critical('Falló el envío de accesos por correo y no se pudo guardar el estado del intento.', [
                    'id_envio' => $envioId,
                    'id_feria' => $idFeria,
                    'id_empresa' => $idEmpresa,
                    'id_usuario' => Auth::user()?->id_usuario,
                    'etapa' => 'actualizacion_historial_fallo',
                    'excepcion' => $estadoException::class,
                    'codigo_error' => (int) $estadoException->getCode(),
                    'nota' => 'Verifique este registro antes de volver a intentar el envío.',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Falló el envío y no se pudo actualizar el historial. Verifique el historial antes de reintentar.',
                ], 502);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el correo. Verifique la configuración del servicio de correo e inténtelo nuevamente.',
            ], 502);
        }

        try {
            $this->actualizarAccionEnvioEmpresa($envioId, 'enviado');
        } catch (\Throwable $exception) {
            Log::critical('El servicio aceptó el correo de accesos, pero no se pudo guardar el estado del envío.', [
                'id_envio' => $envioId,
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::user()?->id_usuario,
                'etapa' => 'actualizacion_historial_exito',
                'excepcion' => $exception::class,
                'codigo_error' => (int) $exception->getCode(),
                'nota' => 'El correo pudo haberse enviado. Verifique el historial antes de reintentar.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El servicio aceptó el correo, pero no se pudo actualizar el historial. Verifique el historial antes de reintentar.',
            ], 502);
        }

        Log::info('El servicio de correo aceptó el mensaje de accesos de una empresa.', [
            'id_envio' => $envioId,
            'id_feria' => $idFeria,
            'id_empresa' => $idEmpresa,
            'id_usuario' => Auth::user()?->id_usuario,
            'cantidad_credenciales' => $datos['cantidad_credenciales'],
            'etapa' => 'transporte_acepto',
            'accion' => 'enviado',
            'nota' => 'La aceptación del servicio de correo no confirma la entrega en la bandeja del destinatario.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Correo aceptado para envío.',
            'data' => ['id_envio' => $envioId],
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    }

    private function datosAccesoWhatsappEmpresa(int $idFeria, int $idEmpresa): array
    {
        $datos = $this->datosCredencialesEmpresa($idFeria, $idEmpresa);

        try {
            $telefono = $this->normalizarCelular($datos['telefono']);
        } catch (ValidationException) {
            $telefono = null;
        }

        if ($telefono === null) {
            Log::warning('No se pudo preparar mensaje de WhatsApp: la persona de contacto no tiene un teléfono válido.', [
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::user()?->id_usuario,
                'campo_telefono_consultado' => 'telefono_responsable',
            ]);

            abort(422, 'La persona de contacto no tiene un teléfono válido. Registre un teléfono de 8 dígitos boliviano o uno con código de país.');
        }

        unset($datos['email']);

        return [...$datos, 'telefono' => preg_replace('/\D+/', '', $telefono)];
    }

    private function datosCredencialesEmpresa(int $idFeria, int $idEmpresa): array
    {
        $feria = Feria::query()->select('id_feria', 'nombre_feria')->findOrFail($idFeria);
        $empresa = Empresa::query()
            ->select('id_empresa', 'nombre_empresa', 'nit', 'usuario', 'pass', 'telefono_responsable', 'email_representante')
            ->findOrFail($idEmpresa);

        abort_unless($this->empresaVinculadaAEvento($idFeria, $idEmpresa), 404);

        $usuarioAcceso = $this->resolverUsuarioAccesoEmpresa($empresa->nit, $empresa->usuario);
        if ($usuarioAcceso === null) {
            Log::warning('No se pudo preparar el envío de accesos: la empresa no tiene NIT ni usuario de acceso.', [
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::user()?->id_usuario,
            ]);

            abort(422, 'La empresa no tiene un NIT ni un usuario de acceso registrado.');
        }

        $password = (string) $empresa->pass;
        if (trim($password) === '' || $this->contrasenaEsHash($password)) {
            $motivo = trim($password) === '' ? 'vacia' : 'protegida';
            Log::warning('No se pudo preparar el envío de accesos: la contraseña no está disponible.', [
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::user()?->id_usuario,
                'motivo' => $motivo,
            ]);

            abort(422, $motivo === 'vacia'
                ? 'La empresa no tiene una contraseña registrada.'
                : 'La contraseña está protegida y no se puede compartir.');
        }

        $detalleEmpresa = $this->detalleEmpresa($idFeria, $idEmpresa)->getData(true)['data'];

        return [
            'telefono' => $empresa->telefono_responsable,
            'email' => $empresa->email_representante,
            'nombre_empresa' => $empresa->nombre_empresa,
            'nombre_feria' => $feria->nombre_feria,
            ...$usuarioAcceso,
            'password' => $password,
            'cantidad_credenciales' => (int) ($detalleEmpresa['credenciales']['asignadas'] ?? 0),
        ];
    }

    private function resolverUsuarioAccesoEmpresa(?string $nit, ?string $usuario): ?array
    {
        $nit = trim((string) $nit);
        if ($nit !== '') {
            return ['usuario' => $nit, 'tipo_usuario' => 'NIT'];
        }

        $usuario = trim((string) $usuario);

        return $usuario !== ''
            ? ['usuario' => $usuario, 'tipo_usuario' => 'Usuario']
            : null;
    }

    private function registrarAccionEnvioEmpresa(int $idFeria, int $idEmpresa, string $canal, string $accion, string $destinatario): int
    {
        try {
            return (int) DB::table('credencial_empresa_envios')->insertGetId([
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'canal' => $canal,
                'accion' => $accion,
                'destinatario' => $destinatario,
                'id_usuario' => Auth::user()->id_usuario,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error al registrar una acción de envío de credenciales de empresa.', [
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::user()?->id_usuario,
                'canal' => $canal,
                'accion' => $accion,
                'excepcion' => $exception::class,
                'codigo_error' => (int) $exception->getCode(),
                'etapa' => 'registro_historial',
            ]);

            throw $exception;
        }
    }

    private function actualizarAccionEnvioEmpresa(int $envioId, string $accion): void
    {
        try {
            DB::table('credencial_empresa_envios')
                ->where('id', $envioId)
                ->update(['accion' => $accion]);
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar el estado de un envío de credenciales de empresa.', [
                'id_envio' => $envioId,
                'accion' => $accion,
                'id_usuario' => Auth::user()?->id_usuario,
                'excepcion' => $exception::class,
                'codigo_error' => (int) $exception->getCode(),
                'etapa' => 'actualizacion_historial',
            ]);

            throw $exception;
        }
    }

    public function historialEnviosEmpresa(Request $request, int $idFeria, int $idEmpresa): JsonResponse
    {
        $usuario = Auth::user();
        $canales = [];
        if ($usuario?->hasPermission('credenciales.empresas.whatsapp.ver')) {
            $canales[] = 'whatsapp';
        }
        if ($usuario?->hasPermission('credenciales.empresas.email.ver')) {
            $canales[] = 'email';
        }

        abort_if($canales === [], 403, 'No autorizado para consultar el historial de envíos.');

        return $this->respuestaHistorialEnviosEmpresa($request, $idFeria, $idEmpresa, $canales);
    }

    private function respuestaHistorialEnviosEmpresa(Request $request, int $idFeria, int $idEmpresa, array $canales): JsonResponse
    {
        abort_unless($this->empresaVinculadaAEvento($idFeria, $idEmpresa), 404);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $paginado = DB::table('credencial_empresa_envios as envios')
                ->leftJoin('usuarios', 'usuarios.id_usuario', '=', 'envios.id_usuario')
                ->where('envios.id_feria', $idFeria)
                ->where('envios.id_empresa', $idEmpresa)
                ->whereIn('envios.canal', $canales)
                ->orderByDesc('envios.created_at')
                ->orderByDesc('envios.id')
                ->select(
                    'envios.id',
                    'envios.canal',
                    'envios.destinatario',
                    'envios.accion',
                    'envios.created_at',
                    'envios.id_usuario',
                    'usuarios.nombre_usuario',
                    'usuarios.nick_usuario',
                )
                ->paginate((int) ($validated['per_page'] ?? 50));
        } catch (\Throwable $exception) {
            Log::error('Error al consultar el historial de envíos de credenciales de una empresa.', [
                'id_feria' => $idFeria,
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::user()?->id_usuario,
                'canales' => $canales,
                'excepcion' => $exception::class,
                'codigo_error' => (int) $exception->getCode(),
                'etapa' => 'consulta_historial',
            ]);

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'data' => collect($paginado->items())->map(fn (object $envio) => [
                'id' => (int) $envio->id,
                'canal' => $envio->canal,
                'destinatario' => $this->enmascararDestinatario($envio->canal, $envio->destinatario),
                'accion' => $envio->accion,
                'usuario' => $envio->nombre_usuario ?: $envio->nick_usuario ?: 'Usuario #'.$envio->id_usuario,
                'fecha' => $envio->created_at,
            ])->values(),
            'meta' => [
                'total' => $paginado->total(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ],
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    }

    private function enmascararDestinatario(string $canal, string $destinatario): string
    {
        if ($canal === 'whatsapp') {
            $digitos = preg_replace('/\D+/', '', $destinatario) ?? '';

            return '+***'.substr($digitos, -4);
        }

        [$local, $dominio] = array_pad(explode('@', $destinatario, 2), 2, '');
        $etiquetasDominio = explode('.', $dominio);
        $tld = array_pop($etiquetasDominio);
        $dominioEnmascarado = collect($etiquetasDominio)
            ->map(fn (string $etiqueta) => ($etiqueta !== '' ? mb_substr($etiqueta, 0, 1) : '').'***')
            ->push($tld)
            ->filter(fn (string $etiqueta) => $etiqueta !== '')
            ->implode('.');

        return ($local !== '' ? mb_substr($local, 0, 1) : '').'***@'.$dominioEnmascarado;
    }

    private function usaTransporteCorreoNoEntregable(string $mailer, array $visitados = []): bool
    {
        if (in_array($mailer, $visitados, true)) {
            return true;
        }

        $visitados[] = $mailer;
        $configuracion = (array) config("mail.mailers.{$mailer}", []);
        $transporte = (string) ($configuracion['transport'] ?? $mailer);
        $url = trim((string) ($configuracion['url'] ?? ''));
        $esquemaUrl = preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $coincidencias)
            ? strtolower($coincidencias[1])
            : '';

        $transportesEntrega = ['smtp', 'ses', 'ses-v2', 'postmark', 'mailgun', 'resend', 'sendmail'];

        if (in_array($transporte, ['failover', 'roundrobin'], true)) {
            $alternativos = (array) ($configuracion['mailers'] ?? []);

            return $alternativos === [] || collect($alternativos)->contains(
                fn ($alternativo) => $this->usaTransporteCorreoNoEntregable((string) $alternativo, $visitados)
            );
        }

        if (! in_array($transporte, $transportesEntrega, true)) {
            return true;
        }

        if ($transporte === 'smtp' && $esquemaUrl !== '' && ! in_array($esquemaUrl, ['smtp', 'smtps'], true)) {
            return true;
        }

        if ($transporte === 'smtp' && ! ($configuracion['require_tls'] ?? false)) {
            return true;
        }

        return false;
    }

    public function buscarEmpresas(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $empresas = Empresa::query()
            ->select('id_empresa', 'nombre_empresa', 'nit', 'usuario')
            ->where('es_activo', 1)
            ->where(function ($query) use ($search) {
                $query->where('nombre_empresa', 'like', "%{$search}%")
                    ->orWhere('nit', 'like', "%{$search}%");
            })
            ->orderBy('nombre_empresa')
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'data' => $empresas]);
    }

    public function buscarExpositorPorCi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ci' => ['required', 'string', 'max:50'],
            'id_feria' => ['nullable', 'integer', 'exists:eventos,id_feria'],
        ]);

        $ci = trim($validated['ci']);
        $expositor = DB::table('expositores')
            ->where('ci', $ci)
            ->select('id_exp', 'ci', 'tipo_documento', 'nombre', 'email', 'com_email', 'celular', 'foto')
            ->first();
        $credencialesEvento = $expositor && ! empty($validated['id_feria'])
            ? DB::table('credenciales as acreditados')
                ->join('empresas', 'empresas.id_empresa', '=', 'acreditados.id_empresa')
                ->where('acreditados.id_expositor', $expositor->id_exp)
                ->where('acreditados.id_feria', $validated['id_feria'])
                ->where('acreditados.estado_credencial', '!=', 3)
                ->select('empresas.nombre_empresa', 'acreditados.tipo_credencial')
                ->get()
                ->map(fn ($credencial) => [
                    'empresa' => $credencial->nombre_empresa,
                    'tipo' => match ((int) $credencial->tipo_credencial) {
                        1 => 'Expositor',
                        2 => 'Prensa',
                        3 => 'Oficial',
                        4 => 'Servicios',
                        5 => 'Negocios',
                        default => 'Sin tipo definido',
                    },
                ])->values()->all()
            : [];

        return response()->json([
            'success' => true,
            'data' => $expositor ? [
                'id_expositor' => (int) $expositor->id_exp,
                'ci' => $expositor->ci,
                'tipo_documento' => $expositor->tipo_documento,
                'nombre' => $expositor->nombre,
                'email' => $expositor->email ?: $expositor->com_email,
                'celular' => $expositor->celular,
                'foto' => $expositor->foto,
                'credenciales_evento' => $credencialesEvento,
            ] : null,
        ]);
    }

    public function acreditarExpositor(Request $request, CredentialPhotoStorage $photoStorage, int $idFeria, int $idEmpresa): JsonResponse
    {
        $validated = $request->validate([
            'id_contrato' => ['nullable', 'integer'],
            'tipo_credencial' => ['required', 'string', 'max:100'],
            'ci' => ['required', 'string', 'max:50'],
            'tipo_documento' => ['nullable', 'string', 'max:50'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'celular' => ['nullable', 'string', 'max:50'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);
        $validated['nombre'] = $this->normalizarNombre($validated['nombre'] ?? '');
        $celular = $this->normalizarCelular($validated['celular'] ?? null);

        $resultado = DB::transaction(function () use ($request, $validated, $celular, $idFeria, $idEmpresa, $photoStorage): array {
            $feria = Feria::query()->lockForUpdate()->findOrFail($idFeria);
            $tipo = trim($validated['tipo_credencial']);
            if (! $this->tiposCredenciales($feria->tipo_credenciales)->contains($tipo)) {
                throw ValidationException::withMessages(['tipo_credencial' => 'El tipo de credencial no está habilitado para este evento.']);
            }

            $empresa = Empresa::query()->findOrFail($idEmpresa);
            $idContrato = ! empty($validated['id_contrato']) ? (int) $validated['id_contrato'] : null;
            $contrato = $idContrato
                ? Contrato::query()->where('id_contrato', $idContrato)->where('id_empresa', $empresa->id_empresa)->where('id_feria', $feria->id_feria)->where('codigo_contrato', '>', 0)->where('total_stands', '>', 0)->lockForUpdate()->firstOrFail()
                : null;
            $vinculacion = EmpresaEventoCredencial::query()
                ->where('id_empresa', $empresa->id_empresa)->where('id_feria', $feria->id_feria)->where('tipo_credencial', $tipo)->where('estado', true)
                ->when($idContrato, fn ($query) => $query->where('id_contrato', $idContrato), fn ($query) => $query->whereNull('id_contrato'))
                ->lockForUpdate()->first();
            if (! $contrato && ! $vinculacion) {
                throw ValidationException::withMessages(['id_contrato' => 'La empresa no tiene una configuración directa para este tipo de credencial.']);
            }

            $tipoCodigo = $this->tipoCredencialCodigo($tipo);
            $limite = (int) ($vinculacion?->cantidad_credenciales ?? $contrato?->nro_credenciales ?? 0)
                + $this->credencialesAdicionalesNetas($feria->id_feria, $empresa->id_empresa, $idContrato, $vinculacion?->id);
            $usadas = DB::table('credenciales')->where('id_empresa', $empresa->id_empresa)->where('id_feria', $feria->id_feria)
                ->when($idContrato, fn ($query) => $query->where('id_contrato', $idContrato), fn ($query) => $query->whereNull('id_contrato'))
                ->where('tipo_credencial', $tipoCodigo)
                ->where('estado_credencial', '!=', 3)
                ->count();
            if ($usadas >= $limite) {
                throw ValidationException::withMessages(['credencial' => 'No quedan credenciales disponibles para este contrato y tipo.']);
            }

            $ci = trim($validated['ci']);
            $expositor = DB::table('expositores')->where('ci', $ci)->lockForUpdate()->first();
            if ($expositor) {
                $idExpositor = (int) $expositor->id_exp;

                $credencialExistente = DB::table('credenciales as acreditados')
                    ->join('empresas', 'empresas.id_empresa', '=', 'acreditados.id_empresa')
                    ->where('acreditados.id_expositor', $idExpositor)
                    ->where('acreditados.id_feria', $feria->id_feria)
                    ->where('acreditados.estado_credencial', '!=', 3)
                    ->select('empresas.nombre_empresa', 'acreditados.tipo_credencial')
                    ->first();
                if ($credencialExistente) {
                    $tipoExistente = match ((int) $credencialExistente->tipo_credencial) {
                        1 => 'Expositor', 2 => 'Prensa', 3 => 'Oficial', 4 => 'Servicios', 5 => 'Negocios',
                        default => 'Sin tipo definido',
                    };
                    throw ValidationException::withMessages([
                        'ci' => "Este expositor ya tiene una credencial en esta feria: {$credencialExistente->nombre_empresa} ({$tipoExistente}).",
                    ]);
                }

                if (empty($validated['nombre']) || empty($validated['tipo_documento'])) {
                    throw ValidationException::withMessages(['expositor' => 'Complete el documento y el nombre completo del expositor.']);
                }
                $datosExpositor = [
                    'tipo_documento' => $validated['tipo_documento'],
                    'nombre' => trim($validated['nombre']),
                    'email' => $validated['email'],
                    'celular' => $celular,
                    'updated_at' => now(),
                ];
                if ($request->hasFile('foto')) {
                    $datosExpositor['foto'] = $photoStorage->store($request->file('foto'));
                }
                DB::table('expositores')->where('id_exp', $idExpositor)->update($datosExpositor);
            } else {
                if (empty($validated['nombre']) || empty($validated['tipo_documento']) || ! $request->hasFile('foto')) {
                    throw ValidationException::withMessages(['expositor' => 'Para un expositor nuevo, complete los datos y cargue una fotografía.']);
                }

                $foto = $photoStorage->store($request->file('foto'));
                $idExpositor = DB::table('expositores')->insertGetId([
                    'nombre' => trim($validated['nombre']), 'ci' => $ci, 'tipo_documento' => $validated['tipo_documento'],
                    'email' => $validated['email'], 'celular' => $celular, 'foto' => $foto, 'estado' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $idCredencial = DB::table('credenciales')->insertGetId([
                'cod_credencial' => 'ACR-'.$feria->id_feria.'-'.($idContrato ?? 'D').'-'.$idExpositor.'-'.now()->format('YmdHis'),
                'id_expositor' => $idExpositor, 'id_contrato' => $idContrato, 'id_empresa' => $empresa->id_empresa, 'id_feria' => $feria->id_feria,
                'com_medio' => '', 'com_dias' => '', 'tipo_credencial' => $tipoCodigo, 'modalidad_credencial' => CredentialModality::normalize((int) $feria->id_feria, (int) ($vinculacion?->tipo_credenciales ?? $contrato?->tipo_credenciales ?? 1)), 'estado_credencial' => 1, 'ingreso' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $token = $this->crearTokenQr($idCredencial);
            DB::table('credencial_estado_historial')->insert([
                'id_credencial' => $idCredencial,
                'accion' => 'creacion',
                'estado_anterior' => 0,
                'estado_nuevo' => 1,
                'motivo' => 'Credencial creada y acreditada para el evento.',
                'version_qr_nueva' => $token['version'],
                'id_usuario' => Auth::user()?->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['id_expositor' => $idExpositor, 'id_credencial' => $idCredencial];
        });

        return response()->json(['success' => true, 'message' => 'Expositor acreditado correctamente.', 'data' => $resultado], 201);
    }

    public function actualizarExpositorAcreditado(Request $request, CredentialPhotoStorage $photoStorage, int $idCredencial): JsonResponse
    {
        $validated = $request->validate([
            'ci' => ['required', 'string', 'max:50'],
            'tipo_documento' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'celular' => ['nullable', 'string', 'max:50'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);
        $validated['nombre'] = $this->normalizarNombre($validated['nombre']);
        $celular = $this->normalizarCelular($validated['celular'] ?? null);

        DB::transaction(function () use ($request, $validated, $celular, $idCredencial, $photoStorage): void {
            $credencial = DB::table('credenciales')->where('id_credencial', $idCredencial)->lockForUpdate()->firstOrFail();
            $ci = trim($validated['ci']);
            $duplicado = DB::table('expositores')
                ->where('ci', $ci)
                ->where('id_exp', '!=', $credencial->id_expositor)
                ->exists();
            if ($duplicado) {
                throw ValidationException::withMessages(['ci' => 'El carnet de identidad ya pertenece a otro expositor.']);
            }

            $datos = [
                'ci' => $ci,
                'tipo_documento' => $validated['tipo_documento'],
                'nombre' => trim($validated['nombre']),
                'email' => $validated['email'],
                'celular' => $celular,
                'updated_at' => now(),
            ];
            if ($request->hasFile('foto')) {
                $datos['foto'] = $photoStorage->store($request->file('foto'));
            }

            DB::table('expositores')->where('id_exp', $credencial->id_expositor)->update($datos);
        });

        return response()->json(['success' => true, 'message' => 'Datos del acreditado actualizados correctamente.']);
    }

    public function cambiarEstadoCredencial(Request $request, int $idCredencial): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'integer', 'in:0,1,2'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);
        $this->autorizarPermiso(match ((int) $validated['estado']) {
            0 => 'credenciales.estado.inhabilitar',
            1 => 'credenciales.estado.habilitar',
            2 => 'credenciales.estado.anular',
        });

        DB::transaction(function () use ($validated, $idCredencial): void {
            $credencial = DB::table('credenciales')
                ->where('id_credencial', $idCredencial)
                ->lockForUpdate()
                ->firstOrFail();
            $estadoNuevo = (int) $validated['estado'];
            $estadoAnterior = (int) $credencial->estado_credencial;

            if ($estadoAnterior === 3) {
                throw ValidationException::withMessages(['estado' => 'La credencial está eliminada y no puede cambiar de estado.']);
            }

            if ($estadoAnterior === $estadoNuevo) {
                throw ValidationException::withMessages(['estado' => 'La credencial ya tiene este estado.']);
            }

            $versionAnterior = $this->versionQrActiva($idCredencial);
            $versionNueva = null;
            if ($estadoNuevo === 2) {
                $this->revocarTokenQrActivo($idCredencial, 'anulacion');
            } elseif ($estadoAnterior === 2 && $estadoNuevo === 1) {
                $versionNueva = $this->crearTokenQr($idCredencial)['version'];
            }

            DB::table('credenciales')->where('id_credencial', $idCredencial)->update([
                'estado_credencial' => $estadoNuevo,
                'updated_at' => now(),
            ]);
            $this->registrarHistorialCredencial(
                $idCredencial,
                $estadoNuevo === 2 ? 'anulacion' : ($estadoAnterior === 2 && $estadoNuevo === 1 ? 'reactivacion_anulada' : 'habilitacion'),
                $estadoAnterior,
                $estadoNuevo,
                trim($validated['motivo']),
                null,
                null,
                null,
                $versionAnterior,
                $versionNueva,
            );
        });

        return response()->json([
            'success' => true,
            'message' => match ((int) $validated['estado']) {
                1 => 'Credencial habilitada correctamente.',
                2 => 'Credencial anulada y código QR revocado correctamente.',
                default => 'Credencial inhabilitada correctamente.',
            },
        ]);
    }

    public function cambiarModalidadCredencial(Request $request, int $idCredencial): JsonResponse
    {
        $validated = $request->validate([
            'modalidad' => ['required', 'integer', 'in:0,1'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $resultado = DB::transaction(function () use ($validated, $idCredencial): array {
            $credencial = DB::table('credenciales')->where('id_credencial', $idCredencial)->lockForUpdate()->firstOrFail();
            if (CredentialModality::isDigitalOnlyEvent((int) $credencial->id_feria) && (int) $validated['modalidad'] !== 1) {
                throw ValidationException::withMessages(['modalidad' => 'Las credenciales del evento 19 solo pueden ser digitales.']);
            }
            $anterior = $this->modalidadEtiqueta($credencial->modalidad_credencial);
            $nueva = $this->modalidadEtiqueta((int) $validated['modalidad']);
            if ($credencial->modalidad_credencial !== null && (int) $credencial->modalidad_credencial === (int) $validated['modalidad']) {
                throw ValidationException::withMessages(['modalidad' => 'La credencial ya tiene esa modalidad.']);
            }

            $versionAnterior = $this->versionQrActiva($idCredencial);
            $this->revocarTokenQrActivo($idCredencial, 'cambio_tipo');
            $token = $this->crearTokenQr($idCredencial);
            DB::table('credenciales')->where('id_credencial', $idCredencial)->update([
                'modalidad_credencial' => (int) $validated['modalidad'],
                'updated_at' => now(),
            ]);
            $this->registrarHistorialCredencial($idCredencial, 'cambio_tipo', (int) $credencial->estado_credencial, (int) $credencial->estado_credencial, 'Cambio de modalidad de credencial.', $validated['observaciones'] ?? null, $anterior, $nueva, $versionAnterior, $token['version']);

            return ['modalidad' => $nueva, 'version_qr' => $token['version']];
        });

        return response()->json(['success' => true, 'message' => 'Modalidad actualizada y código QR renovado.', 'data' => $resultado]);
    }

    public function inhabilitarCredenciales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credenciales' => ['required', 'array', 'min:1', 'max:100'],
            'credenciales.*' => ['integer', 'distinct'],
            'motivo' => ['required', 'string', 'in:Mal uso,Falta de pago'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->autorizarPermiso(count($validated['credenciales']) > 1 ? 'credenciales.seleccionados.inhabilitar' : 'credenciales.estado.inhabilitar');

        DB::transaction(function () use ($validated): void {
            $credenciales = DB::table('credenciales')->whereIn('id_credencial', $validated['credenciales'])->lockForUpdate()->get();
            if ($credenciales->count() !== count($validated['credenciales'])) {
                throw ValidationException::withMessages(['credenciales' => 'Una o más credenciales no existen.']);
            }
            $validosParaInhabilitar = $credenciales->filter(fn ($c) => (int) $c->estado_credencial === 1)->pluck('id_credencial')->all();
            if (empty($validosParaInhabilitar)) {
                throw ValidationException::withMessages(['credenciales' => 'Ninguna de las credenciales seleccionadas puede ser inhabilitada.']);
            }
            DB::table('credenciales')->whereIn('id_credencial', $validosParaInhabilitar)->update(['estado_credencial' => 0, 'updated_at' => now()]);
            foreach ($validosParaInhabilitar as $id) {
                $this->registrarHistorialCredencial($id, count($credenciales) > 1 ? 'inhabilitacion_masiva' : 'inhabilitacion', 1, 0, $validated['motivo'], $validated['observaciones'] ?? null);
            }
        });

        return response()->json(['success' => true, 'message' => 'Credenciales inhabilitadas. Sus códigos QR se conservarán para una futura habilitación.']);
    }

    public function habilitarCredenciales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credenciales' => ['required', 'array', 'min:1', 'max:100'],
            'credenciales.*' => ['integer', 'distinct'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->autorizarPermiso(count($validated['credenciales']) > 1 ? 'credenciales.seleccionados.habilitar' : 'credenciales.estado.habilitar');

        DB::transaction(function () use ($validated): void {
            $credenciales = DB::table('credenciales')
                ->whereIn('id_credencial', $validated['credenciales'])
                ->lockForUpdate()
                ->get();
            if ($credenciales->count() !== count($validated['credenciales'])) {
                throw ValidationException::withMessages(['credenciales' => 'Una o más credenciales no existen.']);
            }
            if ($credenciales->contains(fn ($credencial) => (int) $credencial->estado_credencial !== 0)) {
                throw ValidationException::withMessages(['credenciales' => 'Solo se pueden habilitar credenciales que estén inhabilitadas.']);
            }

            DB::table('credenciales')->whereIn('id_credencial', $validated['credenciales'])->update(['estado_credencial' => 1, 'updated_at' => now()]);
            foreach ($validated['credenciales'] as $id) {
                $this->registrarHistorialCredencial($id, count($validated['credenciales']) > 1 ? 'habilitacion_masiva' : 'habilitacion', 0, 1, 'Credencial habilitada conservando su código QR.', $validated['observaciones'] ?? null);
            }
        });

        return response()->json(['success' => true, 'message' => 'Credenciales habilitadas correctamente. Sus códigos QR se mantienen sin cambios.']);
    }

    public function renovarQrCredencial(Request $request, int $idCredencial): JsonResponse
    {
        $validated = $request->validate(['observaciones' => ['nullable', 'string', 'max:2000']]);
        $resultado = DB::transaction(function () use ($validated, $idCredencial): array {
            $credencial = DB::table('credenciales')->where('id_credencial', $idCredencial)->lockForUpdate()->firstOrFail();
            if ((int) $credencial->estado_credencial !== 1) {
                throw ValidationException::withMessages(['credencial' => 'Solo puede renovar el QR de una credencial activa.']);
            }
            $versionAnterior = $this->versionQrActiva($idCredencial);
            $this->revocarTokenQrActivo($idCredencial, 'renovacion_manual');
            $token = $this->crearTokenQr($idCredencial);
            $this->registrarHistorialCredencial($idCredencial, 'renovacion_qr', 1, 1, 'Renovación manual de código QR.', $validated['observaciones'] ?? null, null, null, $versionAnterior, $token['version']);

            return ['version_qr' => $token['version']];
        });

        return response()->json(['success' => true, 'message' => 'Se generó un nuevo código QR y el anterior fue revocado.', 'data' => $resultado]);
    }

    public function reemplazarCredencial(Request $request, int $idCredencial): JsonResponse
    {
        $validated = $request->validate([
            'modalidad' => ['required', 'integer', 'in:0,1'],
            'motivo' => ['required', 'string', 'in:Perdida,Robada,Dañada,Reemplazada,Otro'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $resultado = DB::transaction(function () use ($validated, $idCredencial): array {
            $credencial = DB::table('credenciales')->where('id_credencial', $idCredencial)->lockForUpdate()->firstOrFail();
            if (CredentialModality::isDigitalOnlyEvent((int) $credencial->id_feria) && (int) $validated['modalidad'] !== 1) {
                throw ValidationException::withMessages(['modalidad' => 'Las credenciales del evento 19 solo pueden ser digitales.']);
            }
            $versionAnterior = $this->versionQrActiva($idCredencial);
            $this->revocarTokenQrActivo($idCredencial, $validated['motivo']);
            $token = $this->crearTokenQr($idCredencial);
            DB::table('credenciales')->where('id_credencial', $idCredencial)->update([
                'modalidad_credencial' => (int) $validated['modalidad'],
                'estado_credencial' => 1,
                'updated_at' => now(),
            ]);
            $this->registrarHistorialCredencial($idCredencial, 'reemplazo_qr', (int) $credencial->estado_credencial, 1, $validated['motivo'], $validated['observaciones'] ?? null, $this->modalidadEtiqueta($credencial->modalidad_credencial), $this->modalidadEtiqueta((int) $validated['modalidad']), $versionAnterior, $token['version']);

            return ['version_qr' => $token['version']];
        });

        return response()->json(['success' => true, 'message' => 'Credencial reemplazada con un nuevo código QR.', 'data' => $resultado]);
    }

    public function validarQrCredencial(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'max:200']]);
        $token = DB::table('credencial_qr_tokens')->where('token_hash', hash('sha256', $validated['token']))->where('activo', true)->first();
        if (! $token) {
            return response()->json(['success' => false, 'valid' => false, 'message' => 'Credencial inválida o reemplazada.'], 422);
        }
        $credencial = DB::table('credenciales as c')->join('expositores as e', 'e.id_exp', '=', 'c.id_expositor')->join('eventos as f', 'f.id_feria', '=', 'c.id_feria')->where('c.id_credencial', $token->id_credencial)->where('c.estado_credencial', 1)->select('c.id_credencial', 'c.id_feria', 'e.nombre', 'f.nombre_feria')->first();
        if (! $credencial) {
            return response()->json(['success' => false, 'valid' => false, 'message' => 'Credencial inhabilitada o no vigente.'], 422);
        }

        return response()->json(['success' => true, 'valid' => true, 'data' => $credencial]);
    }

    public function agregarCredencialesAdicionales(Request $request, int $idFeria, int $idEmpresa): JsonResponse
    {
        $validated = $request->validate([
            'id_contrato' => ['nullable', 'integer'],
            'id_vinculacion' => ['nullable', 'integer'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        $adicional = DB::transaction(function () use ($validated, $idFeria, $idEmpresa): object {
            $empresa = Empresa::query()->findOrFail($idEmpresa);
            $contratoId = ! empty($validated['id_contrato']) ? (int) $validated['id_contrato'] : null;
            $vinculacionId = ! empty($validated['id_vinculacion']) ? (int) $validated['id_vinculacion'] : null;

            if ($contratoId) {
                Contrato::query()
                    ->where('id_contrato', $contratoId)
                    ->where('id_empresa', $empresa->id_empresa)
                    ->where('id_feria', $idFeria)
                    ->firstOrFail();
            } else {
                if (! $vinculacionId) {
                    throw ValidationException::withMessages(['id_vinculacion' => 'Seleccione la vinculación directa para adicionar credenciales.']);
                }

                EmpresaEventoCredencial::query()
                    ->where('id', $vinculacionId)
                    ->where('id_empresa', $empresa->id_empresa)
                    ->where('id_feria', $idFeria)
                    ->whereNull('id_contrato')
                    ->firstOrFail();
            }

            $id = DB::table('cred_adicionales')->insertGetId([
                'feria' => $idFeria,
                'id_empresa' => $empresa->id_empresa,
                'id_empresa_evento_credencial' => $vinculacionId,
                'contrato' => $contratoId,
                'cant_adic' => (int) $validated['cantidad'],
                'tipo_movimiento' => 'adicion',
                'fecha_adic' => now()->toDateString(),
                'obs' => trim($validated['motivo']),
                'id_usuario' => Auth::user()?->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) ['id' => $id];
        });

        return response()->json([
            'success' => true,
            'message' => 'Credenciales adicionales agregadas correctamente.',
            'data' => $adicional,
        ], 201);
    }

    public function disminuirCredencialesAdicionales(Request $request, int $idFeria, int $idEmpresa): JsonResponse
    {
        $validated = $request->validate([
            'id_contrato' => ['nullable', 'integer'],
            'id_vinculacion' => ['nullable', 'integer'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        $movimiento = DB::transaction(function () use ($validated, $idFeria, $idEmpresa): object {
            $empresa = Empresa::query()->findOrFail($idEmpresa);
            $contratoId = ! empty($validated['id_contrato']) ? (int) $validated['id_contrato'] : null;
            $vinculacionId = ! empty($validated['id_vinculacion']) ? (int) $validated['id_vinculacion'] : null;

            if ($contratoId) {
                $contrato = Contrato::query()
                    ->where('id_contrato', $contratoId)
                    ->where('id_empresa', $empresa->id_empresa)
                    ->where('id_feria', $idFeria)
                    ->firstOrFail();
                $baseAsignada = (int) ($contrato->nro_credenciales ?? 0);
                $utilizadas = DB::table('credenciales')
                    ->where('id_contrato', $contrato->id_contrato)
                    ->where('estado_credencial', '!=', 3)
                    ->count();
            } else {
                if (! $vinculacionId) {
                    throw ValidationException::withMessages(['id_vinculacion' => 'Seleccione la vinculación directa para disminuir credenciales.']);
                }

                $vinculacion = EmpresaEventoCredencial::query()
                    ->where('id', $vinculacionId)
                    ->where('id_empresa', $empresa->id_empresa)
                    ->where('id_feria', $idFeria)
                    ->whereNull('id_contrato')
                    ->firstOrFail();
                $baseAsignada = (int) $vinculacion->cantidad_credenciales;
                $utilizadas = DB::table('credenciales')
                    ->where('id_empresa', $empresa->id_empresa)
                    ->where('id_feria', $idFeria)
                    ->whereNull('id_contrato')
                    ->where('tipo_credencial', $this->tipoCredencialCodigo($vinculacion->tipo_credencial))
                    ->where('estado_credencial', '!=', 3)
                    ->count();
            }

            $adicionalesNetos = $this->credencialesAdicionalesNetas($idFeria, $empresa->id_empresa, $contratoId, $vinculacionId);
            $asignadasActuales = $baseAsignada + $adicionalesNetos;
            $disponibles = max(0, $asignadasActuales - $utilizadas);
            $maximoDisminuible = $contratoId
                ? min($disponibles, max(0, $adicionalesNetos))
                : min($disponibles, max(0, $asignadasActuales - 1));
            if ((int) $validated['cantidad'] > $maximoDisminuible) {
                throw ValidationException::withMessages(['cantidad' => "No se puede disminuir esa cantidad. Máximo permitido: {$maximoDisminuible}."]);
            }

            $id = DB::table('cred_adicionales')->insertGetId([
                'feria' => $idFeria,
                'id_empresa' => $empresa->id_empresa,
                'id_empresa_evento_credencial' => $vinculacionId,
                'contrato' => $contratoId,
                'cant_adic' => (int) $validated['cantidad'],
                'tipo_movimiento' => 'disminucion',
                'fecha_adic' => now()->toDateString(),
                'obs' => trim($validated['motivo']),
                'id_usuario' => Auth::user()?->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! $contratoId && $vinculacionId) {
                EmpresaEventoCredencial::query()
                    ->where('id', $vinculacionId)
                    ->update(['cantidad_credenciales' => max(1, $baseAsignada - (int) $validated['cantidad'])]);
            }

            return (object) ['id' => $id];
        });

        return response()->json([
            'success' => true,
            'message' => 'Credenciales disminuidas correctamente.',
            'data' => $movimiento,
        ], 201);
    }

    public function historialCredencialesAdicionales(Request $request, int $idFeria, int $idEmpresa): JsonResponse
    {
        $validated = $request->validate([
            'id_contrato' => ['nullable', 'integer'],
            'id_vinculacion' => ['nullable', 'integer'],
        ]);
        $contratoId = ! empty($validated['id_contrato']) ? (int) $validated['id_contrato'] : null;
        $vinculacionId = ! empty($validated['id_vinculacion']) ? (int) $validated['id_vinculacion'] : null;

        $historial = DB::table('cred_adicionales as adicionales')
            ->leftJoin('usuarios', 'usuarios.id_usuario', '=', 'adicionales.id_usuario')
            ->where('adicionales.feria', $idFeria)
            ->where('adicionales.id_empresa', $idEmpresa)
            ->when($contratoId, fn ($query) => $query->where('adicionales.contrato', $contratoId), fn ($query) => $query->whereNull('adicionales.contrato'))
            ->when($vinculacionId, fn ($query) => $query->where('adicionales.id_empresa_evento_credencial', $vinculacionId), fn ($query) => $query->whereNull('adicionales.id_empresa_evento_credencial'))
            ->select(
                'adicionales.id_adic',
                'adicionales.cant_adic',
                'adicionales.tipo_movimiento',
                'adicionales.fecha_adic',
                'adicionales.obs',
                'adicionales.created_at',
                'adicionales.id_usuario',
                'usuarios.nombre_usuario',
                'usuarios.nick_usuario'
            )
            ->orderByDesc('adicionales.created_at')
            ->get()
            ->map(fn ($registro) => [
                'id' => (int) $registro->id_adic,
                'accion' => $registro->tipo_movimiento === 'disminucion' ? 'Disminución' : 'Adición',
                'cantidad' => (int) $registro->cant_adic,
                'motivo' => $registro->obs,
                'usuario' => $registro->nombre_usuario ?: $registro->nick_usuario ?: ($registro->id_usuario ? 'Usuario #'.$registro->id_usuario : 'Usuario no disponible'),
                'fecha' => $registro->created_at ?: $registro->fecha_adic,
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $historial]);
    }

    public function eliminarCredencial(Request $request, int $idCredencial): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated, $idCredencial): void {
            $credencial = DB::table('credenciales')
                ->where('id_credencial', $idCredencial)
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $credencial->estado_credencial === 3) {
                throw ValidationException::withMessages(['credencial' => 'La credencial ya está eliminada.']);
            }

            DB::table('credencial_estado_historial')->insert([
                'id_credencial' => $idCredencial,
                'accion' => 'eliminacion',
                'estado_anterior' => (int) $credencial->estado_credencial,
                'estado_nuevo' => 3,
                'motivo' => trim($validated['motivo']),
                'id_usuario' => Auth::user()?->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('credenciales')->where('id_credencial', $idCredencial)->update([
                'estado_credencial' => 3,
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Registro de credencial eliminado correctamente.',
        ]);
    }

    public function historialCredencial(Request $request, int $idCredencial): JsonResponse
    {
        $validated = $request->validate([
            'id_feria' => ['nullable', 'integer', 'exists:eventos,id_feria'],
        ]);
        $credencial = DB::table('credenciales')
            ->where('id_credencial', $idCredencial)
            ->select('id_empresa', 'id_expositor')
            ->firstOrFail();
        $historial = DB::table('credencial_estado_historial as historial')
            ->join('credenciales', 'credenciales.id_credencial', '=', 'historial.id_credencial')
            ->join('eventos', 'eventos.id_feria', '=', 'credenciales.id_feria')
            ->leftJoin('usuarios', 'usuarios.id_usuario', '=', 'historial.id_usuario')
            ->where('credenciales.id_empresa', $credencial->id_empresa)
            ->where('credenciales.id_expositor', $credencial->id_expositor)
            ->when(! empty($validated['id_feria']), fn ($query) => $query->where('credenciales.id_feria', $validated['id_feria']))
            ->select(
                'historial.id',
                'historial.id_credencial',
                'historial.accion',
                'historial.tipo_anterior',
                'historial.tipo_nuevo',
                'historial.estado_anterior',
                'historial.estado_nuevo',
                'historial.version_qr_anterior',
                'historial.version_qr_nueva',
                'historial.motivo',
                'historial.observaciones',
                'historial.id_usuario',
                'historial.created_at',
                'eventos.id_feria',
                'eventos.nombre_feria',
                'eventos.fecha_inicio',
                'usuarios.nombre_usuario',
                'usuarios.nick_usuario'
            )
            ->orderByDesc('historial.created_at')
            ->get();

        $descargas = collect();
        if (Schema::hasTable('credencial_digital_descargas')) {
            $descargas = DB::table('credencial_digital_descargas as descarga')
                ->join('credenciales', 'credenciales.id_credencial', '=', 'descarga.id_credencial')
                ->join('eventos', 'eventos.id_feria', '=', 'credenciales.id_feria')
                ->leftJoin('usuarios', 'usuarios.id_usuario', '=', 'descarga.id_usuario')
                ->where('credenciales.id_empresa', $credencial->id_empresa)
                ->where('credenciales.id_expositor', $credencial->id_expositor)
                ->when(! empty($validated['id_feria']), fn ($query) => $query->where('credenciales.id_feria', $validated['id_feria']))
                ->select(
                    'descarga.id',
                    'descarga.id_credencial',
                    'descarga.tipo_descarga',
                    'descarga.id_usuario',
                    'descarga.created_at',
                    'eventos.id_feria',
                    'eventos.nombre_feria',
                    'eventos.fecha_inicio',
                    'usuarios.nombre_usuario',
                    'usuarios.nick_usuario'
                )
                ->get()
                ->map(fn ($descarga) => (object) [
                    'id' => 'descarga-'.$descarga->id,
                    'id_credencial' => $descarga->id_credencial,
                    'accion' => match ($descarga->tipo_descarga) {
                        'impresion' => 'impresion_fisica',
                        'envio_email' => 'envio_email',
                        default => 'descarga_digital',
                    },
                    'estado_anterior' => null,
                    'estado_nuevo' => null,
                    'tipo_anterior' => null,
                    'tipo_nuevo' => null,
                    'version_qr_anterior' => null,
                    'version_qr_nueva' => null,
                    'motivo' => match ($descarga->tipo_descarga) {
                        'lote_zip' => 'Archivo ZIP digital descargado',
                        'envio_email' => 'Credencial digital enviada por correo electrónico',
                        'impresion' => 'Credencial física generada para impresión',
                        default => 'PDF digital individual descargado',
                    },
                    'observaciones' => null,
                    'id_usuario' => $descarga->id_usuario,
                    'created_at' => $descarga->created_at,
                    'id_feria' => $descarga->id_feria,
                    'nombre_feria' => $descarga->nombre_feria,
                    'fecha_inicio' => $descarga->fecha_inicio,
                    'nombre_usuario' => $descarga->nombre_usuario,
                    'nick_usuario' => $descarga->nick_usuario,
                ]);
        }

        $historial = $historial->concat($descargas)->sortByDesc('created_at')->values();

        $eventos = $historial
            ->groupBy('id_feria')
            ->map(function ($registros) {
                $primerRegistro = $registros->first();

                return [
                    'id_feria' => (int) $primerRegistro->id_feria,
                    'nombre' => $primerRegistro->nombre_feria,
                    'fecha_inicio' => $primerRegistro->fecha_inicio,
                    'acciones' => $registros->map(fn ($registro) => [
                        'id' => (string) $registro->id,
                        'accion' => $registro->accion,
                        'accion_label' => match ($registro->accion) {
                            'creacion' => 'Creación',
                            'eliminacion' => 'Eliminación',
                            'descarga_digital' => 'Descarga digital',
                            'envio_email' => 'Envío por correo electrónico',
                            'impresion_fisica' => 'Impresión física',
                            'cambio_tipo' => 'Cambio de tipo',
                            'inhabilitacion' => 'Inhabilitación',
                            'inhabilitacion_masiva' => 'Inhabilitación masiva',
                            'habilitacion_masiva' => 'Habilitación masiva',
                            'reemplazo_qr' => 'Reemplazo de QR',
                            'anulacion' => 'Anulación',
                            'reactivacion_anulada' => 'Reactivación con QR nuevo',
                            'habilitacion' => 'Habilitación',
                            'renovacion_qr' => 'Renovación de QR',
                            default => 'Cambio de estado',
                        },
                        'es_descarga' => in_array($registro->accion, ['descarga_digital', 'envio_email', 'impresion_fisica'], true),
                        'tipo_anterior' => $registro->tipo_anterior,
                        'tipo_nuevo' => $registro->tipo_nuevo,
                        'version_qr_anterior' => $registro->version_qr_anterior,
                        'version_qr_nueva' => $registro->version_qr_nueva,
                        'estado_anterior' => in_array($registro->accion, ['descarga_digital', 'envio_email', 'impresion_fisica'], true) ? '—' : $this->estadoCredencialLabel((int) $registro->estado_anterior),
                        'estado_nuevo' => in_array($registro->accion, ['descarga_digital', 'envio_email', 'impresion_fisica'], true) ? '—' : $this->estadoCredencialLabel((int) $registro->estado_nuevo),
                        'motivo' => trim($registro->motivo.($registro->observaciones ? ' - '.$registro->observaciones : '')),
                        'usuario' => $registro->nombre_usuario ?: $registro->nick_usuario ?: ($registro->id_usuario ? 'Usuario #'.$registro->id_usuario : 'Usuario no disponible'),
                        'fecha' => $registro->created_at,
                    ])->values(),
                ];
            })
            ->sortByDesc('fecha_inicio')
            ->values();

        return response()->json(['success' => true, 'eventos' => $eventos]);
    }

    public function historialAcreditadosEmpresa(int $idEmpresa): JsonResponse
    {
        $historial = DB::table('credenciales as acreditados')
            ->join('eventos', 'eventos.id_feria', '=', 'acreditados.id_feria')
            ->join('empresas', 'empresas.id_empresa', '=', 'acreditados.id_empresa')
            ->join('expositores', 'expositores.id_exp', '=', 'acreditados.id_expositor')
            ->where('acreditados.id_empresa', $idEmpresa)
            ->select(
                'acreditados.id_credencial', 'acreditados.modalidad_credencial',
                'acreditados.id_expositor',
                'acreditados.id_feria',
                'acreditados.tipo_credencial',
                'acreditados.estado_credencial',
                'acreditados.cod_credencial',
                'expositores.nombre', 'expositores.ci', 'expositores.exp',
                'expositores.email', 'expositores.com_email', 'expositores.celular',
                'expositores.tipo_documento', 'expositores.foto',
                'eventos.nombre_feria', 'eventos.fecha_inicio'
            )
            ->orderByDesc('eventos.fecha_inicio')
            ->orderBy('expositores.nombre')
            ->get()
            ->groupBy('id_expositor')
            ->map(fn ($registros) => $registros->first())
            ->values()
            ->map(fn ($acreditado) => [
                'id_credencial' => (int) $acreditado->id_credencial,
                'nombre' => $acreditado->nombre,
                'ci' => $acreditado->ci,
                'exp' => $acreditado->exp,
                'celular' => $acreditado->celular,
                'email' => $acreditado->email ?: $acreditado->com_email,
                'tipo_documento' => $acreditado->tipo_documento,
                'foto' => $acreditado->foto,
                'evento' => $acreditado->nombre_feria,
                'tipo_credencial' => match ((int) $acreditado->tipo_credencial) {
                    1 => 'Expositor', 2 => 'Prensa', 3 => 'Oficial', 4 => 'Servicios', 5 => 'Negocios',
                    default => 'Sin tipo definido',
                },
                'estado_credencial_codigo' => (int) $acreditado->estado_credencial,
                'estado_credencial' => $this->estadoCredencialLabel((int) $acreditado->estado_credencial),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $historial]);
    }

    public function detalleEmpresa(int $idFeria, int $idEmpresa): JsonResponse
    {
        $feria = Feria::query()
            ->select('id_feria', 'nombre_feria', 'codigo_contrato', 'fecha_inicio', 'fecha_fin', 'tipo_credenciales')
            ->findOrFail($idFeria);

        $empresa = Empresa::query()
            ->select('id_empresa', 'nombre_empresa', 'nit', 'usuario', 'telefono', 'email', 'direccion')
            ->findOrFail($idEmpresa);

        $tiposHabilitados = $this->tiposCredenciales($feria->tipo_credenciales);
        $vinculaciones = EmpresaEventoCredencial::query()
            ->where('id_empresa', $empresa->id_empresa)
            ->where('id_feria', $feria->id_feria)
            ->where('estado', true)
            ->when($tiposHabilitados->isNotEmpty(), fn ($query) => $query->whereIn('tipo_credencial', $tiposHabilitados))
            ->get();

        $contratos = Contrato::query()
            ->where('id_empresa', $empresa->id_empresa)
            ->where('id_feria', $feria->id_feria)
            ->where('codigo_contrato', '>', 0)
            ->where('total_stands', '>', 0)
            ->get([
                'id_contrato', 'id_empresa', 'codigo_contrato', 'tipo_credenciales', 'estado_reserva', 'metraje_total', 'nro_credenciales',
                'stand_1', 'stand_2', 'stand_3', 'stand_4', 'stand_5', 'stand_6',
                'stand_7', 'stand_8', 'stand_9', 'stand_10', 'stand_11', 'stand_12',
            ]);

        if ($contratos->isEmpty() && $vinculaciones->isEmpty()) {
            abort(404, 'La empresa no está vinculada al evento seleccionado.');
        }

        $standIds = $contratos
            ->flatMap(fn ($contrato) => collect(range(1, 12))->map(fn ($index) => $contrato->{"stand_{$index}"}))
            ->filter()
            ->unique()
            ->values();
        $stands = Stand::query()
            ->select('id_stand', 'numero_stand', 'id_pabellon')
            ->whereIn('id_stand', $standIds)
            ->get()
            ->keyBy('id_stand');
        $pabellones = Pabellon::query()
            ->select('id_pabellon', 'nombre_pabellon')
            ->whereIn('id_pabellon', $stands->pluck('id_pabellon')->filter()->unique())
            ->get()
            ->keyBy('id_pabellon');
        $vinculacionesPorContrato = $vinculaciones->keyBy('id_contrato');
        $acreditadosPorContrato = DB::table('credenciales as acreditados')
            ->join('expositores', 'expositores.id_exp', '=', 'acreditados.id_expositor')
            ->where('acreditados.id_empresa', $empresa->id_empresa)
            ->where('acreditados.id_feria', $feria->id_feria)
            ->whereIn('acreditados.id_contrato', $contratos->pluck('id_contrato'))
            ->where('acreditados.estado_credencial', '!=', 3)
            ->select(
                'acreditados.id_credencial',
                'acreditados.modalidad_credencial',
                'acreditados.id_contrato',
                'acreditados.id_expositor',
                'acreditados.cod_credencial',
                'acreditados.estado_credencial',
                'acreditados.tipo_credencial',
                'expositores.nombre',
                'expositores.ci',
                'expositores.exp',
                'expositores.email',
                'expositores.com_email',
                'expositores.celular',
                'expositores.tipo_documento',
                'expositores.foto'
            )
            ->orderBy('expositores.nombre')
            ->get()
            ->groupBy('id_contrato');

        $contratosDetalle = $contratos->map(function ($contrato) use ($feria, $tiposHabilitados, $vinculacionesPorContrato, $acreditadosPorContrato, $stands, $pabellones) {
            $vinculacion = $vinculacionesPorContrato->get($contrato->id_contrato);
            $tipoCredencial = $vinculacion?->tipo_credencial ?? $tiposHabilitados->first();
            $standsContrato = collect(range(1, 12))
                ->map(fn ($index) => $stands->get($contrato->{"stand_{$index}"}))
                ->filter()
                ->values();
            $credencialesAdicionales = $this->credencialesAdicionalesNetas((int) $feria->id_feria, (int) $contrato->id_empresa, (int) $contrato->id_contrato, null);
            $credencialesAsignadas = (int) ($vinculacion?->cantidad_credenciales ?? $contrato->nro_credenciales ?? 0) + $credencialesAdicionales;
            $credencialesUtilizadas = $this->credencialesUtilizadas(
                (int) $feria->id_feria,
                (int) $contrato->id_empresa,
                (int) $contrato->id_contrato,
                $vinculacion?->id,
                $tipoCredencial,
            );

            return [
                'id_contrato' => (int) $contrato->id_contrato,
                'codigo_contrato' => (string) $feria->codigo_contrato.str_pad((string) $contrato->codigo_contrato, 5, '0', STR_PAD_LEFT),
                'estado_reserva' => (int) ($contrato->estado_reserva ?? 0),
                'metraje_total' => (float) ($contrato->metraje_total ?? 0),
                'pabellon' => $standsContrato->first() ? ($pabellones->get($standsContrato->first()->id_pabellon)?->nombre_pabellon ?? null) : null,
                'stands' => $standsContrato->pluck('numero_stand')->values(),
                'tipo_credencial' => $tipoCredencial,
                'modalidad_credencial' => CredentialModality::label(
                    (int) $feria->id_feria,
                    $vinculacion?->tipo_credenciales !== null ? (int) $vinculacion->tipo_credenciales : ($contrato->tipo_credenciales === null ? null : (int) $contrato->tipo_credenciales),
                    true,
                ),
                'credenciales_asignadas' => $credencialesAsignadas,
                'credenciales_adicionales' => $credencialesAdicionales,
                'credenciales_utilizadas' => $credencialesUtilizadas,
                'credenciales_disponibles' => max(0, $credencialesAsignadas - $credencialesUtilizadas),
                'acreditados' => ($acreditadosPorContrato->get($contrato->id_contrato) ?? collect())
                    ->filter(fn ($acreditado) => (int) $acreditado->tipo_credencial === $this->tipoCredencialCodigo($tipoCredencial))
                    ->map(fn ($acreditado) => [
                        'id_credencial' => (int) $acreditado->id_credencial,
                        'id_expositor' => (int) $acreditado->id_expositor,
                        'nombre' => $acreditado->nombre,
                        'ci' => $acreditado->ci,
                        'exp' => $acreditado->exp,
                        'email' => $acreditado->email ?: $acreditado->com_email,
                        'celular' => $acreditado->celular,
                        'tipo_documento' => $acreditado->tipo_documento,
                        'foto' => $acreditado->foto,
                        'cod_credencial' => $acreditado->cod_credencial,
                        'estado_credencial_codigo' => (int) $acreditado->estado_credencial,
                        'estado_credencial' => $this->estadoCredencialLabel((int) $acreditado->estado_credencial),
                        'modalidad_credencial' => CredentialModality::label(
                            (int) $feria->id_feria,
                            $acreditado->modalidad_credencial !== null
                                ? (int) $acreditado->modalidad_credencial
                                : ($vinculacion?->tipo_credenciales !== null ? (int) $vinculacion->tipo_credenciales : ($contrato->tipo_credenciales === null ? null : (int) $contrato->tipo_credenciales)),
                            true,
                        ),
                    ])
                    ->values(),
            ];
        })->values();

        $vinculacionesSinContrato = $vinculaciones->whereNull('id_contrato')->values();
        $codigosTiposDirectos = $vinculacionesSinContrato
            ->map(fn ($vinculacion) => $this->tipoCredencialCodigo($vinculacion->tipo_credencial))
            ->unique()
            ->values();
        $credencialesSinContratoPorTipo = $vinculacionesSinContrato->isEmpty()
            ? collect()
            : DB::table('credenciales')
                ->where('id_empresa', $empresa->id_empresa)
                ->where('id_feria', $feria->id_feria)
                ->whereNull('id_contrato')
                ->whereIn('tipo_credencial', $codigosTiposDirectos)
                ->where('estado_credencial', '!=', 3)
                ->select('tipo_credencial', DB::raw('COUNT(*) as total'))
                ->groupBy('tipo_credencial')
                ->pluck('total', 'tipo_credencial');
        $acreditadosDirectosPorTipo = $vinculacionesSinContrato->isEmpty()
            ? collect()
            : DB::table('credenciales as acreditados')
                ->join('expositores', 'expositores.id_exp', '=', 'acreditados.id_expositor')
                ->where('acreditados.id_empresa', $empresa->id_empresa)
                ->where('acreditados.id_feria', $feria->id_feria)
                ->whereNull('acreditados.id_contrato')
                ->whereIn('acreditados.tipo_credencial', $codigosTiposDirectos)
                ->where('acreditados.estado_credencial', '!=', 3)
                ->select(
                    'acreditados.id_credencial', 'acreditados.modalidad_credencial',
                    'acreditados.tipo_credencial',
                    'acreditados.id_expositor',
                    'acreditados.cod_credencial',
                    'acreditados.estado_credencial',
                    'expositores.nombre',
                    'expositores.ci',
                    'expositores.exp',
                    'expositores.email',
                    'expositores.com_email',
                    'expositores.celular',
                    'expositores.tipo_documento',
                    'expositores.foto'
                )
                ->orderBy('expositores.nombre')
                ->get()
                ->groupBy('tipo_credencial');
        $credencialesDirectas = $vinculacionesSinContrato->map(function ($vinculacion) use ($credencialesSinContratoPorTipo, $acreditadosDirectosPorTipo) {
            $tipoCodigo = $this->tipoCredencialCodigo($vinculacion->tipo_credencial);
            $credencialesUtilizadas = (int) ($credencialesSinContratoPorTipo->get($tipoCodigo) ?? 0);
            $credencialesAdicionales = $this->credencialesAdicionalesNetas((int) $vinculacion->id_feria, (int) $vinculacion->id_empresa, null, (int) $vinculacion->id);
            $credencialesAsignadas = (int) $vinculacion->cantidad_credenciales + $credencialesAdicionales;

            return [
                'id_vinculacion' => (int) $vinculacion->id,
                'id_contrato' => null,
                'codigo_contrato' => 'Sin contrato',
                'tipo_credencial' => $vinculacion->tipo_credencial,
                'modalidad_credencial' => CredentialModality::label((int) $vinculacion->id_feria, $vinculacion->tipo_credenciales === null ? null : (int) $vinculacion->tipo_credenciales, true),
                'pabellon' => null,
                'stands' => [],
                'metraje_total' => 0,
                'credenciales_asignadas' => $credencialesAsignadas,
                'credenciales_adicionales' => $credencialesAdicionales,
                'credenciales_utilizadas' => $credencialesUtilizadas,
                'credenciales_disponibles' => max(0, $credencialesAsignadas - $credencialesUtilizadas),
                'acreditados' => ($acreditadosDirectosPorTipo->get($tipoCodigo) ?? collect())
                    ->map(fn ($acreditado) => [
                        'id_credencial' => (int) $acreditado->id_credencial,
                        'id_expositor' => (int) $acreditado->id_expositor,
                        'nombre' => $acreditado->nombre,
                        'ci' => $acreditado->ci,
                        'exp' => $acreditado->exp,
                        'email' => $acreditado->email ?: $acreditado->com_email,
                        'celular' => $acreditado->celular,
                        'tipo_documento' => $acreditado->tipo_documento,
                        'foto' => $acreditado->foto,
                        'cod_credencial' => $acreditado->cod_credencial,
                        'estado_credencial_codigo' => (int) $acreditado->estado_credencial,
                        'estado_credencial' => $this->estadoCredencialLabel((int) $acreditado->estado_credencial),
                        'modalidad_credencial' => CredentialModality::label(
                            (int) $vinculacion->id_feria,
                            $acreditado->modalidad_credencial !== null ? (int) $acreditado->modalidad_credencial : ($vinculacion->tipo_credenciales === null ? null : (int) $vinculacion->tipo_credenciales),
                            true,
                        ),
                    ])
                    ->values(),
            ];
        })->values();
        $credencialesSinContratoUtilizadas = (int) $credencialesDirectas->sum('credenciales_utilizadas');
        $credencialesAsignadas = (int) $contratosDetalle->sum('credenciales_asignadas')
            + (int) $credencialesDirectas->sum('credenciales_asignadas');
        $credencialesUtilizadas = (int) $contratosDetalle->sum('credenciales_utilizadas') + $credencialesSinContratoUtilizadas;

        return response()->json([
            'success' => true,
            'data' => [
                'empresa' => $empresa,
                'evento' => $feria,
                'contratos' => $contratosDetalle,
                'credenciales_sin_contrato' => [
                    'asignadas' => (int) $vinculacionesSinContrato->sum('cantidad_credenciales'),
                    'utilizadas' => $credencialesSinContratoUtilizadas,
                ],
                'credenciales_directas' => $credencialesDirectas,
                'credenciales' => [
                    'asignadas' => $credencialesAsignadas,
                    'utilizadas' => $credencialesUtilizadas,
                    'disponibles' => max(0, $credencialesAsignadas - $credencialesUtilizadas),
                ],
            ],
        ]);
    }

    public function mostrarContrasenaEmpresa(int $idFeria, int $idEmpresa): JsonResponse
    {
        Feria::query()->findOrFail($idFeria);
        $empresa = DB::table('empresas')
            ->select('id_empresa', 'pass')
            ->where('id_empresa', $idEmpresa)
            ->first();

        abort_unless($empresa, 404);

        abort_unless($this->empresaVinculadaAEvento($idFeria, $idEmpresa), 404);

        $password = (string) $empresa->pass;
        $passwordVacia = trim($password) === '';
        $pareceHash = $this->contrasenaEsHash($password);

        if ($passwordVacia || $pareceHash) {
            return response()->json([
                'success' => true,
                'data' => [
                    'available' => false,
                    'message' => $passwordVacia
                        ? 'La empresa no tiene una contraseña registrada.'
                        : 'La contraseña está protegida y no se puede recuperar.',
                ],
            ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        }

        Log::info('Se consultó la contraseña de acceso de una empresa', [
            'id_empresa' => (int) $empresa->id_empresa,
            'id_usuario' => Auth::user()?->id_usuario,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'available' => true,
                'password' => $password,
            ],
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    }

    public function tiposPorEvento(int $idFeria): JsonResponse
    {
        $feria = Feria::query()->select('id_feria', 'tipo_credenciales')->findOrFail($idFeria);
        $tipos = $this->tiposCredenciales($feria->tipo_credenciales)
            ->values()
            ->map(function (string $tipo) use ($idFeria) {
                $diseño = null;

                if (Schema::hasTable('credencial_tipo_diseno')) {
                    $diseño = CredencialTipoDiseño::query()
                        ->where('id_feria', $idFeria)
                        ->where('tipo_credencial', $tipo)
                        ->first();
                }

                return [
                    'value' => $tipo,
                    'title' => $tipo,
                    'tipo_credencial' => $tipo,
                    'modalidad' => 'Digital',
                    'es_digital' => true,
                    'diseño_cargado' => (bool) $diseño,
                    'diseño_url' => $diseño?->ruta ? asset($diseño->ruta) : null,
                    'nombre_archivo' => $diseño?->nombre_archivo,
                ];
            });

        return response()->json(['success' => true, 'data' => $tipos]);
    }

    public function guardarDiseñoTipo(Request $request, int $idFeria, string $tipo): JsonResponse
    {
        if (! Schema::hasTable('credencial_tipo_diseno')) {
            return response()->json([
                'success' => false,
                'message' => 'La tabla de diseños de credenciales aún no está creada. Ejecuta la migración correspondiente.',
            ], 500);
        }

        $feria = Feria::query()->select('id_feria', 'tipo_credenciales')->findOrFail($idFeria);
        $tiposPermitidos = $this->tiposCredenciales($feria->tipo_credenciales);
        $tipoNormalizado = trim((string) $tipo);

        if (! $tiposPermitidos->contains($tipoNormalizado)) {
            return response()->json([
                'success' => false,
                'message' => 'El tipo de credencial no está habilitado para este evento.',
            ], 422);
        }

        $request->validate([
            'archivo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $archivo = $request->file('archivo');
        $image = getimagesize($archivo->getRealPath());
        if ($image === false) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo seleccionado no es una imagen válida.',
            ], 422);
        }

        $ancho = (int) $image[0];
        $alto = (int) $image[1];
        $requiereOptimizacion = $archivo->getSize() > 2 * 1024 * 1024;

        $rutaPrev = CredencialTipoDiseño::query()
            ->where('id_feria', $idFeria)
            ->where('tipo_credencial', $tipoNormalizado)
            ->value('ruta');

        $extension = $requiereOptimizacion ? 'webp' : strtolower((string) $archivo->extension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'El formato de imagen no es válido.',
            ], 422);
        }

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        };
        $nombreArchivo = Str::slug($tipoNormalizado, '-').'-'.$idFeria.'-'.Str::uuid().'.'.$extension;
        $ruta = 'images/credenciales/'.$nombreArchivo;
        $directorio = public_path('images/credenciales');
        $rutaNueva = $directorio.DIRECTORY_SEPARATOR.$nombreArchivo;
        File::ensureDirectoryExists($directorio);

        try {
            if ($requiereOptimizacion) {
                $this->optimizarDiseno($archivo->getRealPath(), $rutaNueva);
            } else {
                $archivo->move($directorio, $nombreArchivo);
            }
        } catch (\Throwable $exception) {
            Log::error('No se pudo guardar el diseño de la credencial.', [
                'id_feria' => $idFeria,
                'tipo_credencial' => $tipoNormalizado,
                'excepcion' => $exception::class,
                'mensaje' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar el diseño de la credencial.',
            ], 500);
        }

        try {
            $diseño = CredencialTipoDiseño::query()->updateOrCreate(
                [
                    'id_feria' => $idFeria,
                    'tipo_credencial' => $tipoNormalizado,
                ],
                [
                    'ruta' => $ruta,
                    'nombre_archivo' => $nombreArchivo,
                    'mime' => $mime,
                    'ancho' => $ancho,
                    'alto' => $alto,
                    'id_usuario_creacion' => Auth::user()?->id_usuario,
                ]
            );
        } catch (\Throwable $exception) {
            File::delete($rutaNueva);
            Log::error('No se pudo actualizar el registro del diseño de credencial.', [
                'id_feria' => $idFeria,
                'tipo_credencial' => $tipoNormalizado,
                'excepcion' => $exception::class,
                'mensaje' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($rutaPrev && $rutaPrev !== $ruta && File::exists(public_path($rutaPrev)) && ! File::delete(public_path($rutaPrev))) {
            Log::warning('No se pudo eliminar el diseño anterior de la credencial.', [
                'id_feria' => $idFeria,
                'tipo_credencial' => $tipoNormalizado,
                'ruta_anterior' => $rutaPrev,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Diseño guardado correctamente.',
            'data' => [
                'id_feria' => $idFeria,
                'tipo_credencial' => $tipoNormalizado,
                'ruta' => $ruta,
                'url' => asset($ruta),
                'diseño_cargado' => true,
                'optimizado' => $requiereOptimizacion,
                'modalidad' => $this->tipoCredencialModalidad($idFeria, $tipoNormalizado),
                'id' => $diseño->id,
            ],
        ]);
    }

    public function descargarCredencialDigital(int $idCredencial): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $acreditados = $this->credencialesParaSalida([$idCredencial], true);
        $acreditado = $acreditados->first();
        $diseno = $this->disenoCredencialDigital($acreditado);
        $contenido = $this->generarImagenCredencialDigital($acreditado, $diseno);
        $directorio = $this->directorioCredencialesTemporales();
        $nombreArchivo = 'credencial-'.Str::slug($acreditado->nombre ?: (string) $acreditado->id_credencial).'-'.Str::uuid().'.png';
        $rutaArchivo = $directorio.DIRECTORY_SEPARATOR.$nombreArchivo;
        File::put($rutaArchivo, $contenido);
        $this->registrarDescargasDigitales([$acreditado->id_credencial], 'png_individual');

        return response()->download($rutaArchivo, 'credencial-'.Str::slug($acreditado->nombre ?: (string) $acreditado->id_credencial).'.png', [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) File::size($rutaArchivo),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ])->deleteFileAfterSend(true);
    }

    public function descargarArchivoDigital(string $archivo): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $nombreArchivo = basename($archivo);
        if (! str_ends_with(strtolower($nombreArchivo), '.zip')) {
            abort(404);
        }

        $rutas = [
            $this->directorioCredencialesTemporales().DIRECTORY_SEPARATOR.$nombreArchivo,
            storage_path('app/credenciales/'.$nombreArchivo),
        ];
        $ruta = collect($rutas)->first(fn (string $candidato): bool => File::isFile($candidato));
        if (! $ruta) {
            abort(404);
        }

        if (File::lastModified($ruta) < now()->subHour()->getTimestamp()) {
            File::delete($ruta);
            abort(404);
        }

        return response()->download($ruta, $archivo, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
        ])
            ->deleteFileAfterSend(true);
    }

    public function imprimirCredencialFisica(int $idCredencial): \Symfony\Component\HttpFoundation\Response
    {
        $acreditado = $this->credencialesParaSalida([$idCredencial])->first();
        abort_unless(! CredentialModality::isDigitalOnlyEvent((int) $acreditado->id_feria), 422, 'Las credenciales del evento 19 solo pueden descargarse en formato digital.');
        $this->registrarDescargasDigitales([$acreditado->id_credencial], 'impresion');

        return response($this->generarPdfCredencialFisica($acreditado), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="credencial-'.Str::slug($acreditado->nombre ?: (string) $acreditado->id_credencial).'.pdf"',
        ]);
    }

    public function imprimirCredencialesFisicas(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'credenciales' => ['required', 'array', 'min:1', 'max:100'],
            'credenciales.*' => ['integer', 'distinct'],
        ]);
        $acreditados = $this->credencialesParaSalida($validated['credenciales']);
        abort_unless($acreditados->every(fn ($acreditado) => ! CredentialModality::isDigitalOnlyEvent((int) $acreditado->id_feria)), 422, 'Las credenciales del evento 19 solo pueden descargarse en formato digital.');
        $this->registrarDescargasDigitales($acreditados->pluck('id_credencial')->all(), 'impresion');
        $nombreEmpresa = Str::slug($acreditados->first()->nombre_empresa ?: 'empresa');

        return response($this->generarPdfCredencialesFisicas($acreditados), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"credenciales-fisicas-{$nombreEmpresa}.pdf\"",
        ]);
    }

    public function descargarCredencialesDigitales(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $validated = $request->validate([
            'credenciales' => ['required', 'array', 'min:1', 'max:100'],
            'credenciales.*' => ['integer', 'distinct'],
        ]);
        $acreditados = $this->credencialesParaSalida($validated['credenciales'], true);
        $directorioTemporal = $this->directorioCredencialesTemporales();
        $this->limpiarDescargasDigitalesTemporales($directorioTemporal);
        $directorioAnterior = storage_path('app/credenciales');
        $this->limpiarDescargasDigitalesTemporales($directorioAnterior);
        if (is_dir($directorioAnterior) && File::files($directorioAnterior) === []) {
            @rmdir($directorioAnterior);
        }
        $rutaZip = $directorioTemporal.DIRECTORY_SEPARATOR.'credenciales-'.Str::uuid().'.zip';
        $zip = new \ZipArchive;

        if ($zip->open($rutaZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'No se pudo preparar el archivo de credenciales digitales.');
        }

        try {
            $nombresUsados = [];
            foreach ($acreditados as $acreditado) {
                $diseno = $this->disenoCredencialDigital($acreditado);
                $baseNombre = 'credencial-'.Str::slug($acreditado->nombre ?: 'sin-nombre');
                $nombresUsados[$baseNombre] = ($nombresUsados[$baseNombre] ?? 0) + 1;
                $nombre = $baseNombre.($nombresUsados[$baseNombre] > 1 ? '-'.$nombresUsados[$baseNombre] : '').'.png';
                if (! $zip->addFromString($nombre, $this->generarImagenCredencialDigital($acreditado, $diseno))) {
                    throw new \RuntimeException("No se pudo agregar la credencial {$acreditado->id_credencial} al archivo ZIP.");
                }
            }
        } finally {
            if (! $zip->close()) {
                File::delete($rutaZip);
                abort(500, 'No se pudo finalizar el archivo de credenciales digitales.');
            }
        }

        if (! File::exists($rutaZip) || File::size($rutaZip) === 0) {
            abort(500, 'No se pudo generar el archivo de credenciales digitales.');
        }

        $verificador = new \ZipArchive;
        $resultadoVerificacion = $verificador->open($rutaZip, \ZipArchive::CHECKCONS);
        if ($resultadoVerificacion !== true || $verificador->numFiles !== $acreditados->count()) {
            if ($resultadoVerificacion === true) {
                $verificador->close();
            }
            File::delete($rutaZip);
            abort(500, 'El archivo ZIP de credenciales no pudo validarse.');
        }
        $verificador->close();

        $this->registrarDescargasDigitales($acreditados->pluck('id_credencial')->all(), 'png_lote');
        $nombreEmpresa = Str::slug($acreditados->first()->nombre_empresa ?: 'empresa');
        $nombreArchivo = "credenciales-digitales-{$nombreEmpresa}.zip";

        if ($request->boolean('archivo')) {
            return response()->download($rutaZip, $nombreArchivo, [
                'Content-Type' => 'application/zip',
                'Content-Length' => (string) File::size($rutaZip),
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ])->deleteFileAfterSend(true);
        }

        return response()->json([
            'success' => true,
            'download_url' => route('credenciales.descarga', ['archivo' => basename($rutaZip)]),
            'filename' => $nombreArchivo,
        ]);
    }

    public function enviarCredencialesDigitales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credenciales' => ['required', 'array', 'min:1', 'max:100'],
            'credenciales.*' => ['integer', 'distinct'],
        ]);
        $acreditados = $this->credencialesParaSalida($validated['credenciales']);
        $errores = [];
        $encoladas = 0;

        foreach ($acreditados as $acreditado) {
            if ($acreditado->modalidad_credencial !== null && (int) $acreditado->modalidad_credencial !== 1) {
                $errores[] = "La credencial de {$acreditado->nombre} no es digital.";

                continue;
            }

            $correo = trim((string) ($acreditado->email ?: $acreditado->com_email));
            if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "El expositor {$acreditado->nombre} no tiene un correo válido.";

                continue;
            }

            try {
                $imagen = $this->generarImagenCredencialDigital($acreditado, $this->disenoCredencialDigital($acreditado));
                EnviarCredencialDigitalJob::dispatch(
                    (int) $acreditado->id_credencial,
                    $correo,
                    (string) $acreditado->nombre,
                    (string) $acreditado->nombre_feria,
                    base64_encode($imagen),
                    Auth::user()?->id_usuario,
                );
                $encoladas++;
            } catch (\Throwable $exception) {
                report($exception);
                $errores[] = "No se pudo enviar la credencial de {$acreditado->nombre}.";
            }
        }

        if ($errores && $encoladas === 0) {
            return response()->json([
                'success' => false,
                'message' => implode(' ', $errores),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Se encolaron {$encoladas} credenciales digitales para envío por correo electrónico."
                .($errores ? ' Pendientes: '.implode(' ', $errores) : ''),
            'encoladas' => $encoladas,
            'pendientes' => $errores,
        ]);
    }

    private function directorioCredencialesTemporales(): string
    {
        $directorio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'intranetfexco-credenciales';
        File::ensureDirectoryExists($directorio);

        return $directorio;
    }

    private function limpiarDescargasDigitalesTemporales(string $directorio): void
    {
        if (! is_dir($directorio)) {
            return;
        }

        foreach (File::files($directorio) as $archivo) {
            if ($archivo->getMTime() < now()->subHour()->getTimestamp()) {
                File::delete($archivo->getPathname());
            }
        }
    }

    public function historialDescargasDigitales(int $idCredencial): JsonResponse
    {
        if (! Schema::hasTable('credencial_digital_descargas')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $historial = DB::table('credencial_digital_descargas as descarga')
            ->leftJoin('usuarios as usuario', 'usuario.id_usuario', '=', 'descarga.id_usuario')
            ->where('descarga.id_credencial', $idCredencial)
            ->orderByDesc('descarga.created_at')
            ->select('descarga.tipo_descarga', 'descarga.created_at', 'usuario.nombre_usuario', 'usuario.nick_usuario')
            ->get()
            ->map(fn ($descarga) => [
                'tipo' => match ($descarga->tipo_descarga) {
                    'lote_zip', 'png_lote' => 'Archivo ZIP de imágenes',
                    'impresion' => 'Impresión física',
                    'envio_email' => 'Envío por correo electrónico',
                    'png_individual' => 'Imagen digital individual',
                    default => 'Salida digital',
                },
                'usuario' => $descarga->nombre_usuario ?: $descarga->nick_usuario ?: 'Usuario no disponible',
                'fecha' => $descarga->created_at,
            ]);

        return response()->json(['success' => true, 'data' => $historial]);
    }

    public function resumenSalidasCredenciales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credenciales' => ['required', 'array', 'max:200'],
            'credenciales.*' => ['integer', 'distinct'],
        ]);

        $resumen = Schema::hasTable('credencial_digital_descargas')
            ? DB::table('credencial_digital_descargas')
                ->whereIn('id_credencial', $validated['credenciales'])
                ->selectRaw('id_credencial, COUNT(*) as cantidad, MAX(created_at) as ultima_fecha, MAX(tipo_descarga) as tipo')
                ->groupBy('id_credencial')
                ->get()
                ->keyBy('id_credencial')
            : collect();

        return response()->json(['success' => true, 'data' => $resumen]);
    }

    private function credencialesParaSalida(array $ids, bool $soloDigitales = false): \Illuminate\Support\Collection
    {

        $acreditados = DB::table('credenciales as credencial')
            ->join('expositores as expositor', 'expositor.id_exp', '=', 'credencial.id_expositor')
            ->join('eventos as evento', 'evento.id_feria', '=', 'credencial.id_feria')
            ->leftJoin('empresas as empresa', 'empresa.id_empresa', '=', 'credencial.id_empresa')
            ->whereIn('credencial.id_credencial', $ids)
            ->where('credencial.estado_credencial', '!=', 3)
            ->when($soloDigitales, fn ($query) => $query->where(function ($sub) {
                $sub->where('credencial.id_feria', CredentialModality::DIGITAL_ONLY_EVENT_ID)
                    ->orWhereNull('credencial.modalidad_credencial')
                    ->orWhere('credencial.modalidad_credencial', 1);
            }))
            ->select(
                'credencial.id_credencial', 'credencial.id_expositor', 'credencial.id_feria', 'credencial.tipo_credencial',
                DB::raw('CASE WHEN credencial.id_feria = '.CredentialModality::DIGITAL_ONLY_EVENT_ID.' THEN 1 ELSE credencial.modalidad_credencial END as modalidad_credencial'),
                'expositor.nombre', 'expositor.ci', 'expositor.exp', 'expositor.tipo_documento', 'expositor.foto',
                'expositor.email', 'expositor.com_email',
                'evento.nombre_feria', 'empresa.nombre_empresa'
            )
            ->get();

        if ($acreditados->count() !== count($ids)) {
            abort(422, 'Una o más credenciales seleccionadas no están disponibles.');
        }

        return $acreditados;
    }

    private function disenoCredencialDigital(object $acreditado): CredencialTipoDiseño
    {
        $tipo = $this->tipoCredencialNombre((int) $acreditado->tipo_credencial);
        $diseno = CredencialTipoDiseño::query()
            ->where('id_feria', $acreditado->id_feria)
            ->where('tipo_credencial', $tipo)
            ->first();

        if (! $diseno || ! File::exists(public_path($diseno->ruta))) {
            abort(422, "No existe un diseño cargado para el tipo de credencial {$tipo}.");
        }

        return $diseno;
    }

    private function generarImagenCredencialDigital(object $acreditado, CredencialTipoDiseño $diseno): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            throw new \RuntimeException('La extensión GD de PHP es necesaria para generar credenciales digitales.');
        }

        $fondo = @imagecreatefromstring((string) File::get(public_path($diseno->ruta)));
        if (! $fondo) {
            throw new \RuntimeException('No se pudo cargar el diseño de la credencial.');
        }

        $ancho = 856;
        $alto = 1333;
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagealphablending($imagen, true);
        imagesavealpha($imagen, true);
        imagecopyresampled($imagen, $fondo, 0, 0, 0, 0, $ancho, $alto, imagesx($fondo), imagesy($fondo));
        imagedestroy($fondo);

        $archivosTemporales = [];
        try {
            $rutaFoto = $this->crearFotoCircularPdf($acreditado->foto, $archivosTemporales);
            if ($rutaFoto) {
                $foto = @imagecreatefrompng($rutaFoto);
                if ($foto) {
                    imagecopyresampled($imagen, $foto, 268, 200, 0, 0, 320, 320, imagesx($foto), imagesy($foto));
                    imagedestroy($foto);
                }
            }

            $blanco = imagecolorallocate($imagen, 255, 255, 255);
            $negro = imagecolorallocate($imagen, 0, 0, 0);
            $this->dibujarTextoImagen($imagen, $acreditado->nombre, 80, 570, 696, 60, $blanco, true);
            $documento = 'CI: '.trim($acreditado->ci.' '.$acreditado->exp);
            $this->dibujarTextoImagen($imagen, $documento, 80, 635, 696, 44, $blanco, false);

            $rutaQr = sys_get_temp_dir().DIRECTORY_SEPARATOR.'credenciales-qr-'.$acreditado->id_credencial.'-'.Str::uuid().'.png';
            $this->generarQrPng($this->tokenQrActual($acreditado->id_credencial)['token'], $rutaQr);
            $archivosTemporales[] = $rutaQr;
            $qr = @imagecreatefrompng($rutaQr);
            if (! $qr) {
                throw new \RuntimeException('No se pudo cargar el código QR generado.');
            }
            imagecopyresampled($imagen, $qr, 203, 760, 0, 0, 450, 450, imagesx($qr), imagesy($qr));
            imagedestroy($qr);

            ob_start();
            imagepng($imagen, null, 6);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($imagen);
            foreach ($archivosTemporales as $archivo) {
                File::delete($archivo);
            }
        }
    }

    private function dibujarTextoImagen(\GdImage $imagen, ?string $texto, int $x, int $y, int $ancho, int $alto, int $color, bool $negrita): void
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return;
        }

        $fuente = $this->fuenteImagen($negrita);
        if (! $fuente || ! function_exists('imagettftext')) {
            $borde = imagecolorallocate($imagen, 0, 0, 0);
            foreach ([[-1, -1], [1, -1], [-1, 1], [1, 1]] as [$desplazamientoX, $desplazamientoY]) {
                imagestring($imagen, 5, $x + $desplazamientoX, $y + $desplazamientoY, $texto, $borde);
            }
            imagestring($imagen, 5, $x, $y, $texto, $color);

            return;
        }

        $tamano = $negrita ? 42 : 28;
        do {
            $caja = imagettfbbox($tamano, 0, $fuente, $texto);
            $anchoTexto = abs($caja[2] - $caja[0]);
            $tamano--;
        } while ($anchoTexto > $ancho && $tamano > 10);

        $caja = imagettfbbox($tamano, 0, $fuente, $texto);
        $anchoTexto = abs($caja[2] - $caja[0]);
        $altoTexto = abs($caja[7] - $caja[1]);
        $posicionX = $x + max(0, intdiv($ancho - $anchoTexto, 2));
        $posicionY = $y + max($altoTexto, intdiv($alto + $altoTexto, 2));
        $borde = imagecolorallocate($imagen, 0, 0, 0);
        foreach ([[-2, -2], [0, -2], [2, -2], [-2, 0], [2, 0], [-2, 2], [0, 2], [2, 2]] as [$desplazamientoX, $desplazamientoY]) {
            imagettftext($imagen, $tamano, 0, $posicionX + $desplazamientoX, $posicionY + $desplazamientoY, $borde, $fuente, $texto);
        }
        imagettftext($imagen, $tamano, 0, $posicionX, $posicionY, $color, $fuente, $texto);
    }

    private function fuenteImagen(bool $negrita): ?string
    {
        $rutas = $negrita
            ? [
                public_path('fonts/DejaVuSans-Bold.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            ]
            : [
                public_path('fonts/DejaVuSans.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
            ];

        foreach ($rutas as $ruta) {
            if (File::exists($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    private function generarPdfCredencialFisica(object $acreditado): string
    {
        $pdf = new \FPDF('L', 'mm', [85.6, 53.98]);
        $this->agregarPaginaCredencialFisica($pdf, $acreditado);

        return $pdf->Output('S');
    }

    private function generarPdfCredencialesFisicas(\Illuminate\Support\Collection $acreditados): string
    {
        $pdf = new \FPDF('L', 'mm', [85.6, 53.98]);
        foreach ($acreditados as $acreditado) {
            $this->agregarPaginaCredencialFisica($pdf, $acreditado);
        }

        return $pdf->Output('S');
    }

    private function agregarPaginaCredencialFisica(\FPDF $pdf, object $acreditado): void
    {
        $archivosTemporales = [];

        try {
            $pdf->AddPage();
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect(0, 0, 85.6, 53.98, 'F');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetXY(6, 10);
            $pdf->MultiCell(47, 4.8, $this->textoPdf($acreditado->nombre), 0, 'L');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(6, 22);
            $pdf->MultiCell(47, 4, $this->textoPdf('CI: '.trim($acreditado->ci.' '.$acreditado->exp)), 0, 'L');
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetXY(6, 29);
            $pdf->MultiCell(47, 4.1, $this->textoPdf($acreditado->nombre_empresa ?: 'Sin institución'), 0, 'L');

            $rutaQr = sys_get_temp_dir().DIRECTORY_SEPARATOR.'credenciales-qr-'.$acreditado->id_credencial.'-'.Str::uuid().'.png';
            $this->generarQrPng($this->tokenQrActual($acreditado->id_credencial)['token'], $rutaQr);
            $archivosTemporales[] = $rutaQr;
            $pdf->Image($rutaQr, 60, 10, 20, 20, 'PNG');

        } finally {
            foreach ($archivosTemporales as $archivo) {
                File::delete($archivo);
            }
        }
    }

    private function escribirTextoCredencial(\FPDF $pdf, string $texto, float $x, float $y, float $ancho, float $alto, int $tamano, bool $negrita): void
    {
        $pdf->SetFont('Arial', $negrita ? 'B' : '', $tamano);
        $pdf->SetTextColor(255, 255, 255);
        foreach ([-0.25, 0.25] as $desplazamientoX) {
            foreach ([-0.25, 0.25] as $desplazamientoY) {
                $pdf->SetXY($x + $desplazamientoX, $y + $desplazamientoY);
                $pdf->Cell($ancho, $alto, $texto, 0, 0, 'C');
            }
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell($ancho, $alto, $texto, 0, 0, 'C');
    }

    private function registrarDescargasDigitales(array $ids, string $tipo): void
    {
        if (! Schema::hasTable('credencial_digital_descargas')) {
            return;
        }

        $ahora = now();
        DB::table('credencial_digital_descargas')->insert(array_map(fn ($id) => [
            'id_credencial' => $id,
            'id_usuario' => Auth::user()?->id_usuario,
            'tipo_descarga' => $tipo,
            'created_at' => $ahora,
        ], $ids));
    }

    private function autorizarPermiso(string $permiso): void
    {
        $usuario = Auth::user();
        if (! $usuario || ! method_exists($usuario, 'hasPermission') || ! $usuario->hasPermission($permiso)) {
            abort(403, 'No autorizado para esta acción.');
        }
    }

    private function modalidadEtiqueta(?int $modalidad): string
    {
        return $modalidad === 0 ? 'Física' : 'Digital';
    }

    private function versionQrActiva(int $idCredencial): ?int
    {
        return DB::table('credencial_qr_tokens')
            ->where('id_credencial', $idCredencial)
            ->where('activo', true)
            ->value('version');
    }

    private function crearTokenQr(int $idCredencial): array
    {
        $version = (int) DB::table('credencial_qr_tokens')->where('id_credencial', $idCredencial)->max('version') + 1;
        $token = 'crd_'.Str::lower(Str::random(48));
        DB::table('credencial_qr_tokens')->insert([
            'id_credencial' => $idCredencial,
            'token_hash' => hash('sha256', $token),
            'token_cifrado' => Crypt::encryptString($token),
            'version' => $version,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['token' => $token, 'version' => $version];
    }

    private function tokenQrActual(int $idCredencial): array
    {
        $registro = DB::table('credencial_qr_tokens')->where('id_credencial', $idCredencial)->where('activo', true)->first();
        if (! $registro) {
            return $this->crearTokenQr($idCredencial);
        }

        try {
            return ['token' => Crypt::decryptString($registro->token_cifrado), 'version' => (int) $registro->version];
        } catch (\Throwable) {
            $this->revocarTokenQrActivo($idCredencial, 'token_no_recuperable');

            return $this->crearTokenQr($idCredencial);
        }
    }

    private function revocarTokenQrActivo(int $idCredencial, string $motivo): void
    {
        DB::table('credencial_qr_tokens')
            ->where('id_credencial', $idCredencial)
            ->where('activo', true)
            ->update([
                'activo' => false,
                'revocado_at' => now(),
                'motivo_revocacion' => $motivo,
                'id_usuario_revocacion' => Auth::user()?->id_usuario,
                'updated_at' => now(),
            ]);
    }

    private function registrarHistorialCredencial(int $idCredencial, string $accion, int $estadoAnterior, int $estadoNuevo, string $motivo, ?string $observaciones = null, ?string $tipoAnterior = null, ?string $tipoNuevo = null, ?int $versionQrAnterior = null, ?int $versionQrNueva = null): void
    {
        DB::table('credencial_estado_historial')->insert([
            'id_credencial' => $idCredencial,
            'accion' => $accion,
            'tipo_anterior' => $tipoAnterior,
            'tipo_nuevo' => $tipoNuevo,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'version_qr_anterior' => $versionQrAnterior,
            'version_qr_nueva' => $versionQrNueva,
            'motivo' => $motivo,
            'observaciones' => $observaciones,
            'id_usuario' => Auth::user()?->id_usuario,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tipoCredencialNombre(int $tipo): string
    {
        return match ($tipo) {
            1 => 'Expositor', 2 => 'Prensa', 3 => 'Oficial', 4 => 'Servicios', 5 => 'Negocios',
            default => 'Sin tipo definido',
        };
    }

    private function textoPdf(?string $texto): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $texto ?: '');
    }

    private function generarQrPng(string $contenido, string $rutaDestino): void
    {
        set_error_handler(static fn () => true, E_DEPRECATED | E_USER_DEPRECATED);
        try {
            $svg = (string) QrCode::format('svg')->size(500)->margin(1)->generate($contenido);
        } finally {
            restore_error_handler();
        }

        if (! preg_match('/viewBox="0 0 ([0-9.]+) ([0-9.]+)"/i', $svg, $viewBox)
            || ! preg_match('/<path[^>]*d="([^"]+)"[^>]*fill="#000000"/i', $svg, $pathMatch)) {
            throw new \RuntimeException('No se pudo generar el código QR.');
        }

        preg_match('/transform="scale\(([0-9.\-]+)\)"/i', $svg, $scaleMatch);
        preg_match('/transform="translate\(([0-9.\-]+),([0-9.\-]+)\)"/i', $svg, $translateMatch);
        $escala = isset($scaleMatch[1]) ? (float) $scaleMatch[1] : 1.0;
        $trasladoX = isset($translateMatch[1]) ? (float) $translateMatch[1] : 0.0;
        $trasladoY = isset($translateMatch[2]) ? (float) $translateMatch[2] : 0.0;
        $modulosAncho = (int) round((float) $viewBox[1] / max($escala, 0.0001));
        $modulosAlto = (int) round((float) $viewBox[2] / max($escala, 0.0001));
        $moduloPx = max(1, (int) round($escala));
        $poligonos = [];

        preg_match_all('/M[^M]*?Z/', $pathMatch[1], $subTrazos);
        foreach ($subTrazos[0] as $subTrazo) {
            preg_match_all('/(-?[0-9]+(?:\.[0-9]+)?)\s+(-?[0-9]+(?:\.[0-9]+)?)/', $subTrazo, $puntos, PREG_SET_ORDER);
            $poligono = array_map(fn (array $punto) => [(float) $punto[1] + $trasladoX, (float) $punto[2] + $trasladoY], $puntos);
            if (count($poligono) >= 3) {
                $poligonos[] = $poligono;
            }
        }

        $imagen = imagecreatetruecolor($modulosAncho * $moduloPx, $modulosAlto * $moduloPx);
        $blanco = imagecolorallocate($imagen, 255, 255, 255);
        $negro = imagecolorallocate($imagen, 0, 0, 0);
        imagefill($imagen, 0, 0, $blanco);

        for ($y = 0; $y < $modulosAlto; $y++) {
            for ($x = 0; $x < $modulosAncho; $x++) {
                $dentro = false;
                foreach ($poligonos as $poligono) {
                    if ($this->puntoDentroPoligonoQr($x + 0.5, $y + 0.5, $poligono)) {
                        $dentro = ! $dentro;
                    }
                }
                if ($dentro) {
                    imagefilledrectangle($imagen, $x * $moduloPx, $y * $moduloPx, ($x + 1) * $moduloPx - 1, ($y + 1) * $moduloPx - 1, $negro);
                }
            }
        }

        imagepng($imagen, $rutaDestino);
        imagedestroy($imagen);
    }

    private function puntoDentroPoligonoQr(float $x, float $y, array $poligono): bool
    {
        $dentro = false;
        $ultimo = count($poligono) - 1;

        for ($indice = 0; $indice < count($poligono); $indice++) {
            [$xActual, $yActual] = $poligono[$indice];
            [$xAnterior, $yAnterior] = $poligono[$ultimo];
            if ((($yActual > $y) !== ($yAnterior > $y)) && ($x < (($xAnterior - $xActual) * ($y - $yActual) / (($yAnterior - $yActual) ?: 1e-12) + $xActual))) {
                $dentro = ! $dentro;
            }
            $ultimo = $indice;
        }

        return $dentro;
    }

    private function crearFotoCircularPdf(?string $foto, array &$archivosTemporales): ?string
    {
        $filename = $foto ? basename(str_replace('\\', '/', $foto)) : '';
        if (! preg_match('/^acreditado_[a-f0-9-]+\.(jpg|jpeg|png)$/i', $filename)) {
            return null;
        }

        $ruta = Storage::disk('credential_photos')->path($filename);
        if (! is_file($ruta) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $origen = @imagecreatefromstring((string) file_get_contents($ruta));
        if (! $origen) {
            return null;
        }

        $anchoOrigen = imagesx($origen);
        $altoOrigen = imagesy($origen);
        $ladoOrigen = min($anchoOrigen, $altoOrigen);
        $lado = min($ladoOrigen, 320);
        $recorte = imagecreatetruecolor($lado, $lado);
        $origenX = intdiv($anchoOrigen - $ladoOrigen, 2);
        $origenY = intdiv($altoOrigen - $ladoOrigen, 2);
        imagecopyresampled($recorte, $origen, 0, 0, $origenX, $origenY, $lado, $lado, $ladoOrigen, $ladoOrigen);
        imagedestroy($origen);
        $circulo = imagecreatetruecolor($lado, $lado);
        imagealphablending($circulo, false);
        imagesavealpha($circulo, true);
        $transparente = imagecolorallocatealpha($circulo, 0, 0, 0, 127);
        imagefill($circulo, 0, 0, $transparente);
        $radio = $lado / 2;
        for ($x = 0; $x < $lado; $x++) {
            for ($y = 0; $y < $lado; $y++) {
                if ((($x - $radio) ** 2) + (($y - $radio) ** 2) <= $radio ** 2) {
                    imagesetpixel($circulo, $x, $y, imagecolorat($recorte, $x, $y));
                }
            }
        }
        $temporal = sys_get_temp_dir().DIRECTORY_SEPARATOR.'credenciales-foto-'.Str::uuid().'.png';
        imagepng($circulo, $temporal);
        imagedestroy($recorte);
        imagedestroy($circulo);
        $archivosTemporales[] = $temporal;

        return $temporal;
    }

    private function optimizarDiseno(string $rutaOrigen, string $rutaDestino): void
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new \RuntimeException('La extensión GD de PHP con soporte WEBP es necesaria para optimizar diseños de credenciales.');
        }

        $origen = imagecreatefromstring((string) file_get_contents($rutaOrigen));
        if ($origen === false) {
            throw new \RuntimeException('No se pudo procesar la imagen seleccionada.');
        }

        imagealphablending($origen, false);
        imagesavealpha($origen, true);
        $resultado = false;

        foreach ([95, 90, 85, 80, 75, 70, 65, 60, 55, 50] as $calidad) {
            $resultado = imagewebp($origen, $rutaDestino, $calidad);
            clearstatcache(true, $rutaDestino);

            if ($resultado && filesize($rutaDestino) <= 2 * 1024 * 1024) {
                break;
            }
        }

        imagedestroy($origen);

        if (! $resultado || filesize($rutaDestino) > 2 * 1024 * 1024) {
            File::delete($rutaDestino);
            throw new \RuntimeException('No se pudo optimizar el diseño a menos de 2 MB manteniendo sus dimensiones.');
        }
    }

    public function storeEmpresa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_feria' => ['required', 'integer', 'exists:eventos,id_feria'],
            'id_empresa' => ['nullable', 'integer', 'exists:empresas,id_empresa'],
            'nombre_empresa' => ['required', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:50'],
            'usuario' => ['nullable', 'string', 'max:50'],
            'pass' => ['nullable', 'string', 'max:255'],
            'tipo_credencial' => ['required', 'string', 'max:100'],
            'tipo_credenciales' => ['required', 'integer', 'in:0,1'],
            'cantidad_credenciales' => ['required', 'integer', 'min:1'],
        ]);

        $result = DB::transaction(function () use ($validated): array {
            $feria = Feria::query()->lockForUpdate()->findOrFail($validated['id_feria']);
            $tipos = collect(explode(';', (string) $feria->tipo_credenciales))
                ->map(fn ($tipo) => trim($tipo))
                ->filter();

            if (! $tipos->contains($validated['tipo_credencial'])) {
                throw ValidationException::withMessages([
                    'tipo_credencial' => 'El tipo de credencial no está habilitado para este evento.',
                ]);
            }

            if (! empty($validated['id_empresa'])) {
                $empresa = Empresa::query()->lockForUpdate()->findOrFail($validated['id_empresa']);
            } else {
                $empresa = Empresa::query()
                    ->whereRaw('LOWER(nombre_empresa) = ?', [mb_strtolower(trim($validated['nombre_empresa']))])
                    ->lockForUpdate()
                    ->first();

                if ($empresa) {
                    throw ValidationException::withMessages([
                        'nombre_empresa' => 'Ya existe una empresa con ese nombre. Selecciónela de la lista.',
                    ]);
                }

                $nit = trim((string) ($validated['nit'] ?? ''));
                if ($nit !== '' && Empresa::query()->where('nit', $nit)->exists()) {
                    throw ValidationException::withMessages(['nit' => 'El NIT ya está registrado en otra empresa.']);
                }

                $empresa = $this->crearEmpresaDesdeCredenciales($validated, Auth::user()?->id_usuario);
            }

            if (EmpresaEventoCredencial::query()
                ->where('id_empresa', $empresa->id_empresa)
                ->where('id_feria', $feria->id_feria)
                ->where('tipo_credencial', $validated['tipo_credencial'])
                ->exists()) {
                throw ValidationException::withMessages(['empresa' => 'La empresa ya está vinculada a este tipo de credencial en el evento.']);
            }

            $vinculacion = EmpresaEventoCredencial::query()->create([
                'id_empresa' => $empresa->id_empresa,
                'id_feria' => $feria->id_feria,
                'tipo_credencial' => $validated['tipo_credencial'],
                'tipo_credenciales' => CredentialModality::normalize((int) $feria->id_feria, (int) $validated['tipo_credenciales']),
                'cantidad_credenciales' => $validated['cantidad_credenciales'],
                'id_contrato' => null,
                'id_usuario_creacion' => Auth::user()?->id_usuario,
            ]);

            return ['empresa' => $empresa, 'vinculacion' => $vinculacion];
        });

        return response()->json([
            'success' => true,
            'message' => 'Empresa vinculada al evento correctamente.',
            'data' => [
                'empresa' => [
                    'id_empresa' => $result['empresa']->id_empresa,
                    'nombre_empresa' => $result['empresa']->nombre_empresa,
                ],
                'vinculacion' => [
                    'id' => $result['vinculacion']->id,
                    'id_feria' => $result['vinculacion']->id_feria,
                    'tipo_credencial' => $result['vinculacion']->tipo_credencial,
                ],
            ],
        ], 201);
    }

    public function destroyEmpresaVinculacion(int $id): JsonResponse
    {
        $vinculacion = EmpresaEventoCredencial::query()->findOrFail($id);
        $credenciales = DB::table('credenciales')
            ->where('id_empresa', $vinculacion->id_empresa)
            ->where('id_feria', $vinculacion->id_feria)
            ->where('tipo_credencial', $this->tipoCredencialCodigo($vinculacion->tipo_credencial))
            ->count();

        if ($credenciales > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la vinculación porque la empresa ya tiene credenciales registradas.',
            ], 422);
        }

        $vinculacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vinculación eliminada correctamente.',
        ]);
    }

    private function tiposCredenciales(?string $tipos): \Illuminate\Support\Collection
    {
        return collect(explode(';', (string) $tipos))
            ->map(fn ($tipo) => trim((string) $tipo))
            ->filter()
            ->unique()
            ->values();
    }

    private function empresaVinculadaAEvento(int $idFeria, int $idEmpresa): bool
    {
        return DB::table('contrato')
            ->where('id_empresa', $idEmpresa)
            ->where('id_feria', $idFeria)
            ->where('codigo_contrato', '>', 0)
            ->where('total_stands', '>', 0)
            ->exists()
            || EmpresaEventoCredencial::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_feria', $idFeria)
                ->where('estado', true)
                ->exists();
    }

    private function contrasenaEsHash(string $password): bool
    {
        $passwordInfo = password_get_info($password);

        return ($passwordInfo['algo'] ?? null) !== null
            || preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{40}|[a-f0-9]{64})$/i', $password) === 1;
    }

    private function tipoCredencialModalidad(int $idFeria, string $tipo): string
    {
        return 'Digital';
    }

    private function credencialesAdicionalesNetas(int $idFeria, int $idEmpresa, ?int $idContrato, ?int $idVinculacion): int
    {
        $movimientoSql = $idContrato
            ? "COALESCE(SUM(CASE WHEN tipo_movimiento = 'disminucion' THEN -cant_adic ELSE cant_adic END), 0) as total"
            : "COALESCE(SUM(CASE WHEN tipo_movimiento = 'adicion' THEN cant_adic ELSE 0 END), 0) as total";

        return (int) DB::table('cred_adicionales')
            ->where('feria', $idFeria)
            ->where('id_empresa', $idEmpresa)
            ->when($idContrato, fn ($query) => $query->where('contrato', $idContrato), fn ($query) => $query->whereNull('contrato'))
            ->when($idVinculacion, fn ($query) => $query->where('id_empresa_evento_credencial', $idVinculacion), fn ($query) => $query->whereNull('id_empresa_evento_credencial'))
            ->selectRaw($movimientoSql)
            ->value('total');
    }

    private function credencialesUtilizadas(int $idFeria, int $idEmpresa, ?int $idContrato, ?int $idVinculacion, string $tipo): int
    {
        return (int) DB::table('credenciales')
            ->where('id_empresa', $idEmpresa)
            ->where('id_feria', $idFeria)
            ->when($idContrato, fn ($query) => $query->where('id_contrato', $idContrato), fn ($query) => $query->whereNull('id_contrato'))
            ->where('tipo_credencial', $this->tipoCredencialCodigo($tipo))
            ->where('estado_credencial', '!=', 3)
            ->count();
    }

    private function tipoCredencialCodigo(string $tipo): int
    {
        return match (mb_strtolower(trim($tipo))) {
            'expositor' => 1,
            'prensa' => 2,
            'oficial' => 3,
            'servicios' => 4,
            'negocios' => 5,
            default => throw ValidationException::withMessages(['tipo_credencial' => 'Tipo de credencial no reconocido.']),
        };
    }

    private function estadoCredencialLabel(int $estado): string
    {
        return match ($estado) {
            1 => 'Activo',
            2 => 'Anulado',
            0 => 'Inhabilitado',
            3 => 'Eliminado',
            default => 'Inhabilitado',
        };
    }

    private function normalizarCelular(?string $celular): ?string
    {
        $valor = trim((string) $celular);
        if ($valor === '') {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $valor);
        if (strlen($digitos) === 8) {
            return '+591'.$digitos;
        }

        if (str_starts_with($digitos, '591') && strlen($digitos) === 11) {
            return '+'.$digitos;
        }

        if (str_starts_with($valor, '+') && preg_match('/^\+[1-9]\d{6,14}$/', '+'.$digitos)) {
            return '+'.$digitos;
        }

        throw ValidationException::withMessages([
            'celular' => 'Ingrese un celular válido: Bolivia requiere 8 dígitos; otros países deben incluir su código de país.',
        ]);
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = preg_replace('/\s+/u', ' ', trim($nombre)) ?: '';

        return mb_convert_case(mb_strtolower($nombre, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function generarUsuarioEmpresa(string $nombre): string
    {
        $base = Str::of($nombre)->ascii()->lower()->replaceMatches('/[^a-z0-9]/', '')->substr(0, 10)->value() ?: 'empresa';
        $usuario = $base;
        $sufijo = 1;

        while (Empresa::query()->where('usuario', $usuario)->exists()) {
            $usuario = substr($base, 0, max(1, 10 - strlen((string) $sufijo))).$sufijo;
            $sufijo++;
        }

        return $usuario;
    }

    private function crearEmpresaDesdeCredenciales(array $validated, ?int $usuarioId): Empresa
    {
        $nombre = trim($validated['nombre_empresa']);
        $nit = trim((string) ($validated['nit'] ?? ''));
        $usuarioSolicitado = trim((string) ($validated['usuario'] ?? ''));
        $usuario = $usuarioSolicitado !== '' && ! Empresa::query()->where('usuario', $usuarioSolicitado)->exists()
            ? $usuarioSolicitado
            : $this->generarUsuarioEmpresa($nombre);

        $empresa = new Empresa;
        $empresa->nombre_empresa = $nombre;
        $empresa->telefono = ' ';
        $empresa->fax = ' ';
        $empresa->email = ' ';
        $empresa->direccion = ' ';
        $empresa->nit = $nit !== '' ? $nit : null;
        $empresa->nombre_responsable = ' ';
        $empresa->nombre_gerente = ' ';
        $empresa->categoria = 0;
        $empresa->rubro = 0;
        $empresa->desc_producto = ' ';
        $empresa->ci_gerente = ' ';
        $empresa->exp_ci_gerente = ' ';
        $empresa->fono_gerente = ' ';
        $empresa->cargo_gerente = ' ';
        $empresa->ci_responsable = ' ';
        $empresa->exp_ci_responsable = ' ';
        $empresa->telefono_responsable = ' ';
        $empresa->email_representante = ' ';
        $empresa->id_tipo_representante = 0;
        $empresa->otro_rubro = ' ';
        $empresa->paises_representante = ' ';
        $empresa->empresa_representante = ' ';
        $empresa->marcas_representante = ' ';
        $empresa->pendiente_llenado = ' ';
        $empresa->pais = '123';
        $empresa->ciudad = '540852';
        $empresa->usuario = $usuario;
        $empresa->pass = ($validated['pass'] ?? null) ?: Str::random(32);
        $empresa->id_usuario_creacion = $usuarioId;
        $empresa->fecha_creacion = now();
        $empresa->fecha_actualizacion_empresa = now();
        $empresa->remember_token = '';
        $empresa->es_activo = 1;
        $empresa->save();

        return $empresa;
    }
}
