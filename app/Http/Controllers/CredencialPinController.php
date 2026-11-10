<?php

namespace App\Http\Controllers;

use App\Exports\CredencialesPinsExport;
use App\Jobs\EnviarPinAcreditacionJob;
use App\Jobs\EnviarPinesAcreditacionEmpresaJob;
use App\Models\Contrato;
use App\Models\CredencialPin;
use App\Models\CredencialPinEnvio;
use App\Models\Empresa;
use App\Models\EmpresaEventoCredencial;
use App\Models\Feria;
use App\Services\CredentialPhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class CredencialPinController extends Controller
{
    private const ALFABETO_PIN = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LONGITUD_PIN = 6;

    private const MAX_INTENTOS_PIN_FALLIDOS_POR_EVENTO = 100;

    private const VENTANA_INTENTOS_PIN_SEGUNDOS = 900;

    public function eventos(): JsonResponse
    {
        $eventos = Feria::query()
            ->select('id_feria', 'nombre_feria', 'fecha_inicio', 'fecha_fin', 'tipo_credenciales')
            ->whereNotNull('tipo_credenciales')
            ->where('tipo_credenciales', '!=', '')
            ->orderByFechaEvento()
            ->get();

        return response()->json(['success' => true, 'data' => $eventos]);
    }

    public function eventosPublicos(): JsonResponse
    {
        $eventos = Feria::query()
            ->select('id_feria', 'nombre_feria', 'fecha_inicio', 'fecha_fin')
            ->whereNotNull('tipo_credenciales')
            ->where('tipo_credenciales', '!=', '')
            ->whereDate('fecha_fin', '>=', now()->toDateString())
            ->orderByFechaEvento('asc')
            ->get();

        return response()->json(['success' => true, 'data' => $eventos]);
    }

    public function index(Request $request, int $idFeria): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'in:disponible,enviado,usado,revocado'],
            'tipo_credencial' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = CredencialPin::query()
            ->with(['empresa:id_empresa,nombre_empresa'])
            ->where('id_feria', $idFeria)
            ->when($validated['estado'] ?? null, fn ($builder, $estado) => $builder->where('estado', $estado))
            ->when($validated['tipo_credencial'] ?? null, fn ($builder, $tipo) => $builder->where('tipo_credencial', $tipo))
            ->when(trim((string) ($validated['search'] ?? '')) !== '', function ($builder) use ($validated) {
                $search = trim((string) $validated['search']);
                $builder->where(function ($nested) use ($search) {
                    $nested->where('nombre_destinatario', 'like', "%{$search}%")
                        ->orWhere('email_destinatario', 'like', "%{$search}%")
                        ->orWhere('celular_destinatario', 'like', "%{$search}%")
                        ->orWhereHas('empresa', fn ($empresa) => $empresa->where('nombre_empresa', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id');

        $paginado = $query->paginate((int) ($validated['per_page'] ?? 50));
        $items = collect($paginado->items())->map(fn (CredencialPin $pin) => $this->presentarPin($pin));

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $paginado->total(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ],
        ]);
    }

    public function resumen(int $idFeria): JsonResponse
    {
        $resumen = CredencialPin::query()
            ->where('id_feria', $idFeria)
            ->whereIn('estado', ['disponible', 'enviado', 'usado'])
            ->select('id_empresa', 'tipo_credencial', DB::raw('COUNT(*) as pines_generados'))
            ->groupBy('id_empresa', 'tipo_credencial')
            ->get()
            ->mapWithKeys(fn ($registro) => [
                $registro->id_empresa.'|'.$registro->tipo_credencial => (int) $registro->pines_generados,
            ]);

        return response()->json(['success' => true, 'data' => $resumen]);
    }

    public function historialGeneraciones(Request $request, int $idFeria, int $idEmpresa): JsonResponse
    {
        $validated = $request->validate([
            'tipo_credencial' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = CredencialPin::query()
            ->leftJoin('usuarios as usuario', 'usuario.id_usuario', '=', 'credencial_pines.generado_por')
            ->where('credencial_pines.id_feria', $idFeria)
            ->where('credencial_pines.id_empresa', $idEmpresa)
            ->when($validated['tipo_credencial'] ?? null, fn ($builder, $tipo) => $builder->where('credencial_pines.tipo_credencial', trim($tipo)))
            ->select([
                'credencial_pines.tipo_credencial',
                'credencial_pines.created_at',
                DB::raw('COUNT(credencial_pines.id) as cantidad'),
                DB::raw("COALESCE(usuario.nombre_usuario, usuario.nick_usuario, 'Usuario no disponible') as usuario"),
            ])
            ->groupBy('credencial_pines.tipo_credencial', 'credencial_pines.created_at', 'usuario.nombre_usuario', 'usuario.nick_usuario')
            ->orderByDesc('credencial_pines.created_at');

        $paginado = $query->paginate((int) ($validated['per_page'] ?? 10));

        return response()->json([
            'success' => true,
            'data' => collect($paginado->items())->map(fn ($generacion) => [
                'tipo_credencial' => $generacion->tipo_credencial,
                'cantidad' => (int) $generacion->cantidad,
                'usuario' => $generacion->usuario,
                'fecha' => $generacion->created_at,
            ]),
            'meta' => [
                'total' => $paginado->total(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ],
        ]);
    }

    public function generar(Request $request, int $idFeria, int $idEmpresa): JsonResponse
    {
        $validated = $request->validate([
            'id_contrato' => ['nullable', 'integer'],
            'id_vinculacion' => ['nullable', 'integer'],
            'tipo_credencial' => ['required', 'string', 'max:100'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $resultado = DB::transaction(function () use ($validated, $idFeria, $idEmpresa): array {
            $feria = Feria::query()->lockForUpdate()->findOrFail($idFeria);
            $empresa = Empresa::query()->findOrFail($idEmpresa);
            $tipo = trim($validated['tipo_credencial']);
            $tipos = $this->tiposCredenciales($feria->tipo_credenciales);

            if (! $tipos->contains($tipo)) {
                throw ValidationException::withMessages(['tipo_credencial' => 'El tipo no está habilitado para este evento.']);
            }

            $contratoId = ! empty($validated['id_contrato']) ? (int) $validated['id_contrato'] : null;
            $vinculacionId = ! empty($validated['id_vinculacion']) ? (int) $validated['id_vinculacion'] : null;
            $contrato = $contratoId
                ? Contrato::query()->where('id_contrato', $contratoId)->where('id_empresa', $idEmpresa)->where('id_feria', $idFeria)->lockForUpdate()->firstOrFail()
                : null;
            $vinculacion = $vinculacionId
                ? EmpresaEventoCredencial::query()
                    ->whereKey($vinculacionId)
                    ->where('id_empresa', $idEmpresa)
                    ->where('id_feria', $idFeria)
                    ->where('tipo_credencial', $tipo)
                    ->where('estado', true)
                    ->when($contratoId, fn ($query) => $query->where('id_contrato', $contratoId))
                    ->lockForUpdate()
                    ->firstOrFail()
                : EmpresaEventoCredencial::query()
                    ->where('id_empresa', $idEmpresa)->where('id_feria', $idFeria)->where('tipo_credencial', $tipo)->where('estado', true)
                    ->when($contratoId, fn ($query) => $query->where('id_contrato', $contratoId), fn ($query) => $query->whereNull('id_contrato'))
                    ->lockForUpdate()->first();

            if ($vinculacion && $contratoId === null && $vinculacion->id_contrato !== null) {
                $contratoId = (int) $vinculacion->id_contrato;
                $contrato = Contrato::query()
                    ->where('id_contrato', $contratoId)
                    ->where('id_empresa', $idEmpresa)
                    ->where('id_feria', $idFeria)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if (! $contrato && ! $vinculacion) {
                throw ValidationException::withMessages(['asignacion' => 'No existe una asignación válida para la empresa y el tipo seleccionado.']);
            }

            if ($contrato && ((int) $contrato->codigo_contrato <= 0 || (int) $contrato->total_stands <= 0)) {
                throw ValidationException::withMessages([
                    'id_contrato' => 'No se pueden generar PINs para un contrato anulado o no formalizado.',
                ]);
            }

            $base = (int) ($vinculacion?->cantidad_credenciales ?? $contrato?->nro_credenciales ?? 0);
            $adicionales = $this->adicionalesNetos($idFeria, $idEmpresa, $contratoId, $vinculacion?->id);
            $asignadas = max(0, $base + $adicionales);
            $tipoCodigo = $this->tipoCredencialCodigo($tipo);
            $usadas = DB::table('credenciales')
                ->where('id_empresa', $idEmpresa)->where('id_feria', $idFeria)
                ->when($contratoId, fn ($query) => $query->where('id_contrato', $contratoId), fn ($query) => $query->whereNull('id_contrato'))
                ->where('tipo_credencial', $tipoCodigo)->where('estado_credencial', '!=', 3)->count();
            $reservadas = CredencialPin::query()
                ->where('id_empresa', $idEmpresa)->where('id_feria', $idFeria)
                ->when($contratoId, fn ($query) => $query->where('id_contrato', $contratoId), fn ($query) => $query->whereNull('id_contrato'))
                ->where('tipo_credencial', $tipo)
                ->whereIn('estado', ['disponible', 'enviado'])
                ->lockForUpdate()->count();
            $disponibles = max(0, $asignadas - $usadas - $reservadas);

            if ((int) $validated['cantidad'] > $disponibles) {
                throw ValidationException::withMessages(['cantidad' => "Solo hay {$disponibles} cupos disponibles para generar PINs."]);
            }

            $pines = [];
            for ($indice = 0; $indice < (int) $validated['cantidad']; $indice++) {
                $pin = $this->crearPinUnico($idFeria);
                $pines[] = CredencialPin::query()->create([
                    'id_feria' => $idFeria,
                    'id_empresa' => $idEmpresa,
                    'id_contrato' => $contratoId,
                    'id_empresa_evento_credencial' => $vinculacion?->id,
                    'tipo_credencial' => $tipo,
                    'modalidad_credencial' => (int) ($vinculacion?->tipo_credenciales ?? $contrato?->tipo_credenciales ?? 1),
                    'pin_hash' => hash('sha256', $pin),
                    'pin_encrypted' => Crypt::encryptString($pin),
                    'estado' => 'disponible',
                    'generado_por' => Auth::user()?->id_usuario,
                ]);
            }

            return ['cantidad' => count($pines), 'ids' => collect($pines)->pluck('id')->values()];
        });

        return response()->json(['success' => true, 'message' => "Se generaron {$resultado['cantidad']} PINs.", 'data' => $resultado], 201);
    }

    public function exportar(Request $request, int $idFeria): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $validated = $request->validate([
            'estado' => ['nullable', 'string', 'in:disponible,enviado,usado,revocado'],
            'tipo_credencial' => ['nullable', 'string', 'max:100'],
        ]);
        $pines = CredencialPin::query()->with('empresa:id_empresa,nombre_empresa')->where('id_feria', $idFeria)
            ->when($validated['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($validated['tipo_credencial'] ?? null, fn ($query, $tipo) => $query->where('tipo_credencial', $tipo))
            ->orderBy('id')->get()->map(fn (CredencialPin $pin) => $this->presentarPin($pin));
        $feria = Feria::query()->findOrFail($idFeria);

        return Excel::download(new CredencialesPinsExport($pines, $feria->nombre_feria), 'pines-'.Str::slug($feria->nombre_feria).'-'.now()->format('Ymd_His').'.xlsx');
    }

    public function imprimir(Request $request, int $idFeria): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'pines' => ['nullable', 'array', 'max:500'],
            'pines.*' => ['integer', 'distinct'],
        ]);
        $query = CredencialPin::query()->with('empresa:id_empresa,nombre_empresa')->where('id_feria', $idFeria);
        if (! empty($validated['pines'])) {
            $query->whereIn('id', $validated['pines']);
        } else {
            $query->whereIn('estado', ['disponible', 'enviado']);
        }
        $pines = $query->orderBy('id')->get();
        if ($pines->isEmpty()) {
            abort(422, 'No existen PINs disponibles para imprimir.');
        }

        $pdf = new \FPDF('P', 'mm', 'A4');
        foreach ($pines->chunk(8) as $grupo) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, $this->textoPdf(Feria::query()->whereKey($idFeria)->value('nombre_feria') ?: 'Autoacreditación'), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, $this->textoPdf('Tarjetas de activación de credencial'), 0, 1, 'C');
            $xInicial = 12;
            $yInicial = 28;
            $ancho = 88;
            $alto = 50;
            foreach ($grupo->values() as $indice => $pin) {
                $columna = $indice % 2;
                $fila = intdiv($indice, 2);
                $x = $xInicial + ($columna * 98);
                $y = $yInicial + ($fila * 57);
                $pdf->SetDrawColor(190, 190, 190);
                $pdf->Rect($x, $y, $ancho, $alto);
                $pdf->SetXY($x + 5, $y + 5);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell($ancho - 10, 5, $this->textoPdf($pin->empresa?->nombre_empresa ?: 'Institución'), 0, 1, 'L');
                $pdf->SetX($x + 5);
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell($ancho - 10, 5, $this->textoPdf($pin->tipo_credencial.' · '.((int) $pin->modalidad_credencial === 0 ? 'Física' : 'Digital')), 0, 1, 'L');
                $pdf->SetX($x + 5);
                $pdf->SetFont('Arial', 'B', 22);
                $pdf->Cell($ancho - 10, 14, $this->textoPdf(Crypt::decryptString($pin->pin_encrypted)), 0, 1, 'C');
                $pdf->SetX($x + 5);
                $pdf->SetFont('Arial', '', 7);
                $pdf->MultiCell($ancho - 10, 4, $this->textoPdf('Ingrese este PIN en la plataforma de autoacreditación para registrar su credencial.'), 0, 'C');
            }
        }

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pines-acreditacion-'.$idFeria.'.pdf"',
        ]);
    }

    public function enviar(Request $request, int $idPin): JsonResponse
    {
        $validated = $request->validate([
            'canal' => ['required', 'string', 'in:email,whatsapp'],
            'destinatario' => ['required', 'string', 'max:255'],
        ]);
        if ($validated['canal'] === 'whatsapp') {
            if (! config('services.whatsapp.url') || ! config('services.whatsapp.token')) {
                throw ValidationException::withMessages(['canal' => 'WhatsApp requiere configurar un proveedor oficial en services.whatsapp.']);
            }
            throw ValidationException::withMessages(['canal' => 'El proveedor de WhatsApp aún no está habilitado en este entorno.']);
        }

        if (! filter_var($validated['destinatario'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['destinatario' => 'Ingrese un correo electrónico válido.']);
        }
        $datosEnvio = DB::transaction(function () use ($validated, $idPin): array {
            $pin = CredencialPin::query()->with(['feria', 'empresa'])->lockForUpdate()->findOrFail($idPin);
            if (in_array($pin->estado, ['usado', 'revocado'], true)) {
                throw ValidationException::withMessages(['pin' => 'No se puede enviar un PIN usado o revocado.']);
            }

            CredencialPinEnvio::query()->create([
                'id_pin' => $pin->id,
                'id_feria' => $pin->id_feria,
                'id_empresa' => $pin->id_empresa,
                'tipo_credencial' => $pin->tipo_credencial,
                'correo_destinatario' => $validated['destinatario'],
                'tipo_envio' => 'individual',
                'canal' => 'email',
                'cantidad_pines' => 1,
                'id_usuario' => Auth::user()?->id_usuario,
            ]);
            $pin->update([
                'estado' => 'enviado',
                'email_destinatario' => $validated['destinatario'],
                'ultimo_canal_envio' => 'email',
                'ultimo_envio_at' => now(),
            ]);

            return [
                'id' => (int) $pin->id,
                'pin' => Crypt::decryptString($pin->pin_encrypted),
                'feria' => (string) ($pin->feria?->nombre_feria ?: 'evento'),
                'empresa' => (string) ($pin->empresa?->nombre_empresa ?: 'institución'),
                'tipo' => (string) $pin->tipo_credencial,
            ];
        });

        EnviarPinAcreditacionJob::dispatch(
            $datosEnvio['id'], $validated['destinatario'], $datosEnvio['pin'],
            $datosEnvio['feria'], $datosEnvio['empresa'], $datosEnvio['tipo'], Auth::user()?->id_usuario,
        );

        return response()->json(['success' => true, 'message' => 'PIN enviado por correo correctamente.']);
    }

    public function enviarEmpresa(Request $request, int $idFeria, int $idEmpresa): JsonResponse
    {
        $validated = $request->validate([
            'tipo_credencial' => ['required', 'string', 'max:100'],
            'destinatario' => ['required', 'email', 'max:255'],
        ]);
        $tipo = trim($validated['tipo_credencial']);
        $loteUuid = (string) Str::uuid();
        $datosEnvio = DB::transaction(function () use ($idFeria, $idEmpresa, $tipo, $validated, $loteUuid): array {
            $feria = Feria::query()->findOrFail($idFeria);
            $empresa = Empresa::query()->findOrFail($idEmpresa);
            $pines = CredencialPin::query()
                ->where('id_feria', $idFeria)->where('id_empresa', $idEmpresa)
                ->where('tipo_credencial', $tipo)->whereIn('estado', ['disponible', 'enviado'])
                ->orderBy('id')->lockForUpdate()->get(['id', 'id_feria', 'id_empresa', 'tipo_credencial']);

            if ($pines->isEmpty()) {
                throw ValidationException::withMessages(['pines' => 'No hay PINs disponibles para enviar en esta asignación.']);
            }

            $ahora = now();
            CredencialPinEnvio::query()->insert($pines->map(fn ($pin) => [
                'id_pin' => $pin->id, 'id_feria' => $pin->id_feria, 'id_empresa' => $pin->id_empresa,
                'lote_uuid' => $loteUuid, 'tipo_credencial' => $pin->tipo_credencial,
                'correo_destinatario' => $validated['destinatario'], 'tipo_envio' => 'empresa',
                'canal' => 'email', 'cantidad_pines' => $pines->count(),
                'id_usuario' => Auth::user()?->id_usuario, 'created_at' => $ahora,
            ])->all());
            CredencialPin::query()->whereIn('id', $pines->pluck('id'))->update([
                'estado' => 'enviado', 'email_destinatario' => $validated['destinatario'],
                'ultimo_canal_envio' => 'email', 'ultimo_envio_at' => $ahora,
            ]);

            return [
                'ids' => $pines->pluck('id')->all(),
                'feria' => (string) ($feria->nombre_feria ?: 'evento'),
                'empresa' => (string) ($empresa->nombre_empresa ?: 'institución'),
                'cantidad' => $pines->count(),
            ];
        });

        EnviarPinesAcreditacionEmpresaJob::dispatch(
            $datosEnvio['ids'], $validated['destinatario'], $datosEnvio['feria'],
            $datosEnvio['empresa'], Auth::user()?->id_usuario, $loteUuid,
        );

        return response()->json(['success' => true, 'message' => "Se enviaron {$datosEnvio['cantidad']} PINs por correo."]);
    }

    public function historialEnvios(Request $request, int $idFeria): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tipo_envio' => ['nullable', 'string', 'in:individual,empresa'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = CredencialPinEnvio::query()
            ->leftJoin('credencial_pines as pin', 'pin.id', '=', 'credencial_pin_envios.id_pin')
            ->leftJoin('empresas as empresa', 'empresa.id_empresa', '=', 'credencial_pin_envios.id_empresa')
            ->leftJoin('usuarios as usuario', 'usuario.id_usuario', '=', 'credencial_pin_envios.id_usuario')
            ->where('credencial_pin_envios.id_feria', $idFeria)
            ->when($validated['tipo_envio'] ?? null, fn ($builder, $tipo) => $builder->where('credencial_pin_envios.tipo_envio', $tipo))
            ->when(trim((string) ($validated['search'] ?? '')) !== '', function ($builder) use ($validated) {
                $search = trim((string) $validated['search']);
                $builder->where(function ($nested) use ($search) {
                    $nested->where('credencial_pin_envios.correo_destinatario', 'like', "%{$search}%")
                        ->orWhere('empresa.nombre_empresa', 'like', "%{$search}%")
                        ->orWhere('usuario.nombre_usuario', 'like', "%{$search}%")
                        ->orWhere('usuario.nick_usuario', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('credencial_pin_envios.created_at')
            ->select([
                'credencial_pin_envios.id',
                'credencial_pin_envios.id_pin',
                'credencial_pin_envios.lote_uuid',
                'credencial_pin_envios.tipo_credencial',
                'credencial_pin_envios.correo_destinatario',
                'credencial_pin_envios.tipo_envio',
                'credencial_pin_envios.cantidad_pines',
                'credencial_pin_envios.created_at',
                'pin.pin_encrypted',
                'empresa.nombre_empresa',
                DB::raw("COALESCE(usuario.nombre_usuario, usuario.nick_usuario, 'Usuario no disponible') as nombre_usuario"),
            ]);

        $paginado = $query->paginate((int) ($validated['per_page'] ?? 50));
        $items = collect($paginado->items())->map(fn ($envio) => [
            'id' => $envio->id,
            'pin' => $envio->pin_encrypted ? Crypt::decryptString($envio->pin_encrypted) : '—',
            'empresa' => $envio->nombre_empresa ?: '—',
            'tipo_credencial' => $envio->tipo_credencial,
            'correo' => $envio->correo_destinatario,
            'tipo_envio' => $envio->tipo_envio,
            'cantidad_pines' => (int) $envio->cantidad_pines,
            'lote_uuid' => $envio->lote_uuid,
            'usuario' => $envio->nombre_usuario,
            'fecha' => $envio->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $paginado->total(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ],
        ]);
    }

    public function validarPublico(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_feria' => ['required', 'integer', 'exists:eventos,id_feria'],
            'pin' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6}$/'],
        ]);
        $idFeria = (int) $validated['id_feria'];
        $this->asegurarIntentosPinDisponibles($idFeria);

        try {
            $pin = $this->pinDisponible($validated['pin'], $idFeria);
        } catch (ValidationException $exception) {
            $this->registrarIntentoPinFallido($idFeria);

            throw $exception;
        }

        return response()->json(['success' => true, 'data' => [
            'id' => $pin->id,
            'id_feria' => (int) $pin->id_feria,
            'evento' => $pin->feria?->nombre_feria,
            'empresa' => $pin->empresa?->nombre_empresa,
            'tipo_credencial' => $pin->tipo_credencial,
            'modalidad' => (int) $pin->modalidad_credencial === 0 ? 'Física' : 'Digital',
        ]]);
    }

    public function registrarPublico(Request $request, CredentialPhotoStorage $photoStorage): JsonResponse
    {
        $validated = $request->validate([
            'id_feria' => ['required', 'integer', 'exists:eventos,id_feria'],
            'pin' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6}$/'],
            'ci' => ['required', 'string', 'max:50'],
            'tipo_documento' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'celular' => ['nullable', 'string', 'max:50'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);
        $validated['nombre'] = $this->normalizarNombre($validated['nombre']);
        $idFeria = (int) $validated['id_feria'];
        $this->asegurarIntentosPinDisponibles($idFeria);

        try {
            $resultado = DB::transaction(function () use ($request, $validated, $photoStorage): array {
                $pin = CredencialPin::query()
                    ->with(['feria', 'empresa'])
                    ->where('id_feria', $validated['id_feria'])
                    ->where('pin_hash', hash('sha256', strtoupper(trim($validated['pin']))))
                    ->whereHas('feria', fn ($query) => $query->whereDate('fecha_fin', '>=', now()->toDateString()))
                    ->lockForUpdate()
                    ->first();
                if (! $pin || $pin->estado !== 'disponible') {
                    throw ValidationException::withMessages(['pin' => 'El PIN no es válido o ya fue utilizado.']);
                }

                $feria = $pin->feria;
                $expositor = DB::table('expositores')->where('ci', trim($validated['ci']))->lockForUpdate()->first();
                if ($expositor && DB::table('credenciales')->where('id_expositor', $expositor->id_exp)->where('id_feria', $pin->id_feria)->where('estado_credencial', '!=', 3)->exists()) {
                    throw ValidationException::withMessages(['ci' => 'Este documento ya tiene una credencial en este evento.']);
                }

                $celular = $this->normalizarCelular($validated['celular'] ?? null);
                $foto = $photoStorage->store($request->file('foto'));
                $idExpositor = $expositor?->id_exp;

                if ($idExpositor) {
                    DB::table('expositores')->where('id_exp', $idExpositor)->update([
                        'tipo_documento' => $validated['tipo_documento'], 'nombre' => trim($validated['nombre']), 'email' => $validated['email'], 'celular' => $celular, 'foto' => $foto, 'updated_at' => now(),
                    ]);
                } else {
                    $idExpositor = DB::table('expositores')->insertGetId([
                        'ci' => trim($validated['ci']), 'tipo_documento' => $validated['tipo_documento'], 'nombre' => trim($validated['nombre']), 'email' => $validated['email'], 'celular' => $celular, 'foto' => $foto, 'estado' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                $tipoCodigo = $this->tipoCredencialCodigo($pin->tipo_credencial);
                $idCredencial = DB::table('credenciales')->insertGetId([
                    'cod_credencial' => 'ACR-'.$pin->id_feria.'-PIN-'.$pin->id.'-'.$idExpositor,
                    'id_expositor' => $idExpositor, 'id_contrato' => $pin->id_contrato, 'id_empresa' => $pin->id_empresa, 'id_feria' => $pin->id_feria,
                    'com_medio' => '', 'com_dias' => '', 'tipo_credencial' => $tipoCodigo, 'modalidad_credencial' => $pin->modalidad_credencial, 'estado_credencial' => 1, 'ingreso' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->crearTokenQr($idCredencial);
                if (DB::getSchemaBuilder()->hasTable('credencial_estado_historial')) {
                    DB::table('credencial_estado_historial')->insert(['id_credencial' => $idCredencial, 'accion' => 'creacion', 'estado_anterior' => 0, 'estado_nuevo' => 1, 'motivo' => 'Autoacreditación mediante PIN.', 'id_usuario' => null, 'created_at' => now(), 'updated_at' => now()]);
                }
                $pin->update(['estado' => 'usado', 'id_credencial' => $idCredencial, 'nombre_destinatario' => trim($validated['nombre']), 'celular_destinatario' => $celular, 'email_destinatario' => $validated['email'], 'usado_at' => now(), 'intentos_fallidos' => 0]);

                return ['id_credencial' => $idCredencial, 'evento' => $feria?->nombre_feria, 'empresa' => $pin->empresa?->nombre_empresa];
            });
        } catch (ValidationException $exception) {
            if (array_key_exists('pin', $exception->errors())) {
                $this->registrarIntentoPinFallido($idFeria);
            }

            throw $exception;
        }

        return response()->json(['success' => true, 'message' => 'Autoacreditación completada correctamente.', 'data' => $resultado], 201);
    }

    private function asegurarIntentosPinDisponibles(int $idFeria): void
    {
        if (RateLimiter::tooManyAttempts($this->claveIntentosPin($idFeria), self::MAX_INTENTOS_PIN_FALLIDOS_POR_EVENTO)) {
            abort(429, 'Se alcanzó el límite temporal de intentos de autoacreditación para este evento.');
        }
    }

    private function registrarIntentoPinFallido(int $idFeria): void
    {
        $key = $this->claveIntentosPin($idFeria);
        RateLimiter::hit($key, self::VENTANA_INTENTOS_PIN_SEGUNDOS);

        $this->asegurarIntentosPinDisponibles($idFeria);
    }

    private function claveIntentosPin(int $idFeria): string
    {
        return 'autoacreditacion-pin-fallos-feria-'.$idFeria;
    }

    private function presentarPin(CredencialPin $pin): array
    {
        return [
            'id' => $pin->id,
            'pin' => Crypt::decryptString($pin->pin_encrypted),
            'id_empresa' => $pin->id_empresa,
            'empresa' => $pin->empresa?->nombre_empresa,
            'id_contrato' => $pin->id_contrato,
            'tipo_credencial' => $pin->tipo_credencial,
            'modalidad' => (int) $pin->modalidad_credencial === 0 ? 'Física' : 'Digital',
            'estado' => $pin->estado,
            'nombre_destinatario' => $pin->nombre_destinatario,
            'celular_destinatario' => $pin->celular_destinatario,
            'email_destinatario' => $pin->email_destinatario,
            'usado_at' => $pin->usado_at,
            'ultimo_envio_at' => $pin->ultimo_envio_at,
            'ultimo_canal_envio' => $pin->ultimo_canal_envio,
        ];
    }

    private function pinDisponible(string $valor, int $idFeria): CredencialPin
    {
        $pin = CredencialPin::query()
            ->with(['feria', 'empresa'])
            ->where('id_feria', $idFeria)
            ->where('pin_hash', hash('sha256', strtoupper(trim($valor))))
            ->whereHas('feria', fn ($query) => $query->whereDate('fecha_fin', '>=', now()->toDateString()))
            ->first();
        if (! $pin || $pin->estado !== 'disponible') {
            throw ValidationException::withMessages(['pin' => 'El PIN no es válido o ya fue utilizado.']);
        }

        return $pin;
    }

    private function crearPinUnico(int $idFeria): string
    {
        do {
            $pin = '';
            $longitudAlfabeto = strlen(self::ALFABETO_PIN);
            for ($indice = 0; $indice < self::LONGITUD_PIN; $indice++) {
                $pin .= self::ALFABETO_PIN[random_int(0, $longitudAlfabeto - 1)];
            }
        } while (CredencialPin::query()->where('id_feria', $idFeria)->where('pin_hash', hash('sha256', $pin))->exists());

        return $pin;
    }

    private function crearTokenQr(int $idCredencial): void
    {
        $version = (int) DB::table('credencial_qr_tokens')->where('id_credencial', $idCredencial)->max('version') + 1;
        $token = 'crd_'.Str::lower(Str::random(48));
        DB::table('credencial_qr_tokens')->insert(['id_credencial' => $idCredencial, 'token_hash' => hash('sha256', $token), 'token_cifrado' => Crypt::encryptString($token), 'version' => $version, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function adicionalesNetos(int $idFeria, int $idEmpresa, ?int $idContrato, ?int $idVinculacion): int
    {
        return (int) DB::table('cred_adicionales')->where('feria', $idFeria)
            ->when(
                $idContrato,
                fn ($query) => $query->where('contrato', $idContrato),
                fn ($query) => $query->where('id_empresa', $idEmpresa)
                    ->whereNull('contrato')
                    ->when($idVinculacion, fn ($nested) => $nested->where('id_empresa_evento_credencial', $idVinculacion), fn ($nested) => $nested->whereNull('id_empresa_evento_credencial')),
            )
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_movimiento = 'disminucion' THEN -cant_adic ELSE cant_adic END), 0) as total")->value('total');
    }

    private function tiposCredenciales(?string $tipos): \Illuminate\Support\Collection
    {
        return collect(explode(';', (string) $tipos))->map(fn ($tipo) => trim((string) $tipo))->filter()->unique()->values();
    }

    private function tipoCredencialCodigo(string $tipo): int
    {
        return match (mb_strtolower(trim($tipo))) {
            'expositor' => 1, 'prensa' => 2, 'oficial' => 3, 'servicios' => 4, 'negocios' => 5,
            default => throw ValidationException::withMessages(['tipo_credencial' => 'Tipo de credencial no reconocido.']),
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
        throw ValidationException::withMessages(['celular' => 'Ingrese un celular válido.']);
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = preg_replace('/\s+/u', ' ', trim($nombre)) ?: '';

        return mb_convert_case(mb_strtolower($nombre, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function textoPdf(?string $texto): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $texto ?: '');
    }
}
