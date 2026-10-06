<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\Feria;
use App\Models\LimiteCredencial;
use App\Models\Ocupacion;
use App\Models\Pabellon;
use App\Models\Stand;
use App\Models\Backedempresa;
use App\Models\Credencial;
use App\Http\Requests\StoreContratoRequest;
use App\Events\ContratoActualizado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ContratoController extends Controller
{
    /**
     * Obtener todas las ferias activas
     */
    public function getFerias()
    {
        try {
            $ferias = Feria::select('id_feria', 'nombre_feria', 'codigo_contrato', 'estado_feria')
                ->orderByFechaEvento()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $ferias
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ferias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un contrato específico con su empresa
     */
    public function show($id)
    {
        try {
            $contrato = Contrato::with(['empresa'])->find($id);
            
            if (!$contrato) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $contrato
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener contrato', [
                'id_contrato' => $id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el contrato.'
            ], 500);
        }
    }

    /**
     * Obtener contrato público con validación de clave
     * Este método es para acceso público sin autenticación
     */
    public function getContratoPublico($idContrato, $clave)
    {
        try {
            // Buscar contrato con la clave
            $contrato = Contrato::where('id_contrato', $idContrato)
                ->where('clave', $clave)
                ->select(
                    'id_contrato', 'id_empresa', 'id_feria', 'clave', 'codigo_contrato',
                    'productos', 'perfil_visitante', 'tipo_credenciales',
                    'medio_comunicacion', 'como_entero', 'tipo_expositor',
                    'marca_principal', 'pais_principal', 'marcas_secundarios'
                )
                ->with(['empresa' => function ($query) {
                    $query->select(
                        'id_empresa', 'nombre_empresa', 'nit', 'direccion', 
                        'telefono', 'fax', 'email', 'web', 'pais', 'ciudad',
                        'nombre_responsable', 'ci_responsable', 'exp_ci_responsable', 'telefono_responsable',
                        'email_representante', 'id_tipo_representante',
                        'nombre_gerente', 'ci_gerente', 'exp_ci_gerente', 'fono_gerente', 'cargo_gerente',
                        'rubro', 'subrubro', 'otro_rubro', 'cluster', 'aniversario',
                        'nr_escritura', 'fecha_nr_escritura', 'matricula',
                        'nr_poder', 'nr_notaria', 'fecha_nr_poder', 'distrito'
                    );
                }])
                ->first();

            if (!$contrato) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato no encontrado o clave incorrecta'
                ], 404);
            }

            // Verificar si el contrato ya tiene código generado
            $contratoYaGenerado = !empty($contrato->codigo_contrato);

            return response()->json([
                'success' => true,
                'data' => $contrato,
                'contrato_ya_generado' => $contratoYaGenerado,
                'codigo_contrato' => $contrato->codigo_contrato
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener contrato público', [
                'id_contrato' => $idContrato,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el contrato.'
            ], 500);
        }
    }

    /**
     * Guardar formulario público - Réplica de formulario_lleno
     * Guarda datos de contratos y empresas desde el formulario público
     */
    public function guardarFormularioPublico($idContrato, $clave, StoreContratoRequest $request)
    {
        try {
            // ==================== VALIDACIÓN ====================
            // El FormRequest ya valida automáticamente antes de llegar aquí
            // Si hay errores de validación, Laravel retorna automáticamente un 422
            // con los errores estructurados
            
            // Validar contrato y clave
            $contrato = Contrato::where('id_contrato', $idContrato)
                ->where('clave', $clave)
                ->first();

            if (!$contrato) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato no encontrado o clave incorrecta'
                ], 404);
            }

            // Verificar que el contrato no tenga código generado
            if (!empty($contrato->codigo_contrato)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este contrato ya fue generado y no puede ser modificado. Por favor contacte al área comercial de FEXCO.'
                ], 403);
            }

            // Obtener empresa
            $empresa = Empresa::find($contrato->id_empresa);
            if (!$empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada'
                ], 404);
            }

            // Guardar datos antes de cambios (para auditoría)
            $datosAntes = $empresa->toArray();
            $contratoAntes = $contrato->toArray();

            // ========== ACTUALIZAR CONTRATO ==========
            
            // Productos
            $contrato->productos = $request->input('productos') ?? '';
            
            // Perfil visitante
            $contrato->perfil_visitante = $request->input('perfil_visitante') ?? '';
            
            // Medio de comunicación
            $contrato->medio_comunicacion = $request->input('medio_comunicacion') ?? 0;
            
            // Cómo se enteró
            $contrato->como_entero = $request->input('como_entero') ?? 0;
            
            // Tipo de expositor
            $contrato->tipo_expositor = $request->input('tipo_expositor') ?? 0;

            // Eventos anteriores al 21 usan credenciales físicas por defecto.
            $contrato->tipo_credenciales = (int) $contrato->id_feria >= 21
                ? $request->input('tipo_credenciales')
                : 0;

            // Procesar marcas nacionales: mn = [nombre1, nombre2, ...]
            $marcasNacionales = $request->input('mn', []);
            $marcaPrincipal = '';
            if (is_array($marcasNacionales)) {
                foreach ($marcasNacionales as $marca) {
                    if (!empty($marca)) {
                        $marcaPrincipal .= $marca . ';';
                    }
                }
                $marcaPrincipal = rtrim($marcaPrincipal, ';');
            }
            $contrato->marca_principal = $marcaPrincipal;

            // Procesar marcas internacionales: pp = [nombre1, nombre2, ...], pais_pp = [pais1, pais2, ...]
            $marcasIntNombres = $request->input('pp', []);
            $paisesIntNombres = $request->input('pais_pp', []);
            $paisPrincipal = '';
            if (is_array($marcasIntNombres)) {
                foreach ($marcasIntNombres as $index => $marca) {
                    if (!empty($marca)) {
                        $paisValue = $paisesIntNombres[$index] ?? '';
                        $paisPrincipal .= $marca . ';' . $paisValue . ';';
                    }
                }
                $paisPrincipal = rtrim($paisPrincipal, ';');
            }
            $contrato->pais_principal = $paisPrincipal;

            // Procesar marcas multinacionales: mu = [nombre1, nombre2, ...], pais_mu = [pais1, pais2, ...]
            $marcasMultiNombres = $request->input('mu', []);
            $paisesMultiNombres = $request->input('pais_mu', []);
            $marcasSecundarios = '';
            if (is_array($marcasMultiNombres)) {
                foreach ($marcasMultiNombres as $index => $marca) {
                    if (!empty($marca)) {
                        $paisValue = $paisesMultiNombres[$index] ?? '';
                        $marcasSecundarios .= $marca . ';' . $paisValue . ';';
                    }
                }
                $marcasSecundarios = rtrim($marcasSecundarios, ';');
            }
            $contrato->marcas_secundarios = $marcasSecundarios;

            $contrato->save();

            // ========== ACTUALIZAR EMPRESA ==========
            
            // Datos generales
            $empresa->nombre_empresa = $request->input('nombre_empresa') ?? $empresa->nombre_empresa;
            $empresa->nit = $request->input('nit') ?? $empresa->nit;
            $empresa->direccion = $request->input('direccion') ?? $empresa->direccion;
            $empresa->telefono = $request->input('telefono') ?? $empresa->telefono;
            $empresa->fax = $request->input('fax') ?? $empresa->fax;
            $empresa->email = $request->input('email') ?? $empresa->email;
            $empresa->web = $request->input('web') ?? $empresa->web;

            // País y ciudad
            $empresa->pais = $request->input('pais_id') ?? $empresa->pais;
            $empresa->ciudad = $request->input('ciudad_id') ?? $empresa->ciudad;

            // Categoría (cluster)
            $empresa->cluster = $request->input('cluster') ?? $empresa->cluster;

            // Aniversario
            $empresa->aniversario = $request->input('aniversario') ?? $empresa->aniversario;

            // Datos legales
            $empresa->nr_escritura = $request->input('nr_escritura') ?? $empresa->nr_escritura;
            $empresa->fecha_nr_escritura = $request->input('fecha_nr_escritura') ?? $empresa->fecha_nr_escritura;
            $empresa->matricula = $request->input('matricula') ?? $empresa->matricula;
            $empresa->nr_poder = $request->input('nr_poder') ?? $empresa->nr_poder;
            $empresa->nr_notaria = $request->input('nr_notaria') ?? $empresa->nr_notaria;
            $empresa->fecha_nr_poder = $request->input('fecha_nr_poder') ?? $empresa->fecha_nr_poder;
            $empresa->distrito = $request->input('distrito') ?? $empresa->distrito;

            // Rubros
            $empresa->rubro = $request->input('rubro') ?? $empresa->rubro;
            $empresa->subrubro = $request->input('subrubro') ?? $empresa->subrubro;
            $empresa->otro_rubro = $request->input('otro_rubro') ?? $empresa->otro_rubro;

            // Actividad principal
            $empresa->id_tipo_representante = $request->input('actividad_principal') ?? $empresa->id_tipo_representante;

            // Representante legal
            $empresa->nombre_gerente = $request->input('nombre_gerente') ?? $empresa->nombre_gerente;
            $empresa->ci_gerente = $request->input('ci_gerente') ?? $empresa->ci_gerente;
            $empresa->exp_ci_gerente = $request->input('exp_ci_gerente') ?? $empresa->exp_ci_gerente;
            $empresa->fono_gerente = $request->input('fono_gerente') ?? $empresa->fono_gerente;
            $empresa->cargo_gerente = $request->input('cargo_gerente') ?? $empresa->cargo_gerente;

            // Responsable de contacto
            $empresa->nombre_responsable = $request->input('nombre_responsable') ?? $empresa->nombre_responsable;
            $empresa->telefono_responsable = $request->input('telefono_responsable') ?? $empresa->telefono_responsable;
            $empresa->email_representante = $request->input('email_representante') ?? $empresa->email_representante;

            // ========== DETECTAR CAMPOS PENDIENTES ==========
            $pendientes = [];
            
            if (empty($empresa->nombre_empresa)) $pendientes[] = 'nombre_empresa';
            if (empty($empresa->nit)) $pendientes[] = 'nit';
            if (empty($empresa->direccion)) $pendientes[] = 'direccion';
            if (empty($empresa->pais)) $pendientes[] = 'pais';
            if (empty($empresa->ciudad)) $pendientes[] = 'ciudad';
            if (empty($empresa->nombre_gerente)) $pendientes[] = 'nombre_gerente';
            if (empty($empresa->ci_gerente)) $pendientes[] = 'ci_gerente';
            if (empty($empresa->rubro)) $pendientes[] = 'rubro';
            if (empty($contrato->productos)) $pendientes[] = 'productos';

            $empresa->pendiente_llenado = implode(';', $pendientes);

            if ($this->empresaTieneCambios($datosAntes, $empresa->toArray())) {
                $empresa->fecha_actualizacion_empresa = now();
                $empresa->save();

                // ========== AUDITORÍA ==========
                $auditoria = new Backedempresa();
                $auditoria->id_empresa = $empresa->id_empresa;
                $auditoria->modificado_por = 'Llenado por Enlace Público';
                $auditoria->datos_antes = json_encode($datosAntes);
                $auditoria->datos_despues = json_encode($empresa->toArray());
                $auditoria->fecha = now();
                $auditoria->save();
            }

            event(new \App\Events\ActualizacionDatos([
                'empresa' => $empresa->nombre_empresa ?? 'Empresa',
                'feria' => optional($contrato->feria)->nombre_feria ?? 'Feria',
                'contrato' => (string) $contrato->id_contrato,
                'fecha' => now()->format('d/m/Y H:i'),
                'usuario' => 'Formulario público',
                'url' => $request->getSchemeAndHttpHost() . '/contrato/llenado/' . $contrato->id_contrato,
            ]));

            // ========== RESPUESTA EXITOSA ==========
            return response()->json([
                'success' => true,
                'message' => 'Formulario guardado correctamente',
                'data' => [
                    'id_contrato' => $contrato->id_contrato,
                    'empresa_nombre' => $empresa->nombre_empresa
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar formulario público de contrato', [
                'id_contrato' => $idContrato,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar el formulario.'
            ], 500);
        }
    }
    public function getContratosByFeria(Request $request, $idFeria)
    {
        try {
            // Obtener información de la feria (campos necesarios)
            $feria = Feria::select('id_feria', 'codigo_contrato', 'nombre_feria', 'estado_feria')
                ->find($idFeria);
            
            if (!$feria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feria no encontrada'
                ], 404);
            }

            // Obtener contratos con empresa relacionada (seleccionar campos específicos)
            $contratos = Contrato::select(
                'id_contrato', 'id_feria', 'id_empresa', 'codigo_contrato', 'estado_reserva',
                'descuento', 'observaciones', 'precio_unit', 'precio_total', 'total_stands',
                'stand_1', 'stand_2', 'stand_3', 'stand_4', 'stand_5', 'stand_6',
                'stand_7', 'stand_8', 'stand_9', 'stand_10', 'stand_11', 'stand_12',
                'nro_credenciales', 'productos', 'clave'
            )
                ->with(['empresa' => function ($query) {
                    $query->select('id_empresa', 'nombre_empresa', 'nombre_responsable', 'nombre_gerente', 'nit', 'ciudad');
                }])
                ->where('id_feria', $idFeria)
                ->orderBy('codigo_contrato', 'asc')
                ->get();

            // Obtener todos los stands (campos esenciales)
            $stands = Stand::select('id_stand', 'numero_stand', 'area_stand', 'id_pabellon', 'feria')
                ->where('feria', $idFeria)
                ->get()
                ->keyBy('id_stand');

            // Obtener todos los pabellones
            $pabellones = Pabellon::select('id_pabellon', 'nombre_pabellon', 'feria')
                ->where('feria', $idFeria)
                ->get()
                ->keyBy('id_pabellon');

            // Procesar cada contrato
            $contratosFormateados = $contratos->map(function ($contrato) use ($feria, $stands, $pabellones) {
                // Obtener stands del contrato
                $standsContrato = [];
                $pabellonNombre = null;
                $metrajeTotal = 0;
                
                // Verificar si el contrato está anulado (sin stands)
                $esAnulado = $contrato->total_stands == 0;

                if (!$esAnulado) {
                    // Recopilar stands del contrato
                    for ($i = 1; $i <= 12; $i++) {
                        $standField = "stand_$i";
                        $idStand = $contrato->$standField;
                        
                        if ($idStand && isset($stands[$idStand])) {
                            $stand = $stands[$idStand];
                            $standsContrato[] = $stand->numero_stand;
                            
                            // Obtener el pabellón del primer stand
                            if (!$pabellonNombre && isset($pabellones[$stand->id_pabellon])) {
                                $pabellonNombre = $pabellones[$stand->id_pabellon]->nombre_pabellon;
                            }
                            
                            $metrajeTotal += $stand->area_stand ?? 0;
                        }
                    }
                }

                // Formatear número de contrato
                $numeroContrato = $feria->codigo_contrato . str_pad($contrato->codigo_contrato, 5, '0', STR_PAD_LEFT);

                // Calcular precios
                $descuento = $contrato->descuento ?? 0;
                $precioUnitario = $contrato->precio_unit ?? 0;
                $precioTotal = $contrato->precio_total ?? 0;
                
                // Si hay descuento, calcular el precio con descuento
                $precioConDescuento = null;
                if ($descuento > 0) {
                    $precioConDescuento = $precioTotal - ($precioTotal * ($descuento / 100));
                }

                return [
                    'id_contrato' => $contrato->id_contrato,
                    'empresa' => [
                        'nombre' => $contrato->empresa->nombre_empresa ?? 'Sin empresa',
                        'id' => $contrato->id_empresa
                    ],
                    'contrato' => [
                        'numero' => $numeroContrato,
                        'codigo' => $contrato->codigo_contrato,
                    ],
                    'clave' => $contrato->clave,
                    'anulado' => $esAnulado,
                    'pabellon' => $pabellonNombre ?? 'N/A',
                    'stands' => $standsContrato,
                    'stands_texto' => !empty($standsContrato) ? implode(', ', $standsContrato) : 'N/A',
                    'area' => [
                        'metraje_total' => $metrajeTotal,
                        'precio_unitario' => $precioUnitario,
                        'descuento' => $descuento,
                        'precio_total' => $precioTotal,
                        'precio_con_descuento' => $precioConDescuento
                    ],
                    'contacto' => [
                        'responsable' => $contrato->empresa->nombre_responsable ?? 'N/A',
                        'gerente' => $contrato->empresa->nombre_gerente ?? 'N/A'
                    ],
                    'productos' => $contrato->productos ?? 'N/A',
                    'credenciales' => $contrato->nro_credenciales ?? 0,
                    'estado_feria' => $feria->estado_feria,
                    'observaciones' => $contrato->observaciones ?? ''
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'feria' => [
                        'id' => $feria->id_feria,
                        'nombre' => $feria->nombre_feria,
                        'codigo' => $feria->codigo_contrato,
                        'estado' => $feria->estado_feria
                    ],
                    'contratos' => $contratosFormateados
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los contratos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las empresas para el select
     */
    public function getEmpresas()
    {
        try {
            $empresas = Empresa::select('id_empresa', 'nombre_empresa', 'nombre_responsable', 'nombre_gerente')
                ->where('es_activo', 1)
                ->orderBy('nombre_empresa', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $empresas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las empresas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva empresa
     */
    public function storeEmpresa(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre_empresa' => 'required|string|max:255',
                'nombre_responsable' => 'nullable|string|max:255',
                'nombre_gerente' => 'nullable|string|max:255',
                'telefono' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
                'direccion' => 'nullable|string|max:500',
                'nit' => 'nullable|string|max:50',
                'pais' => 'nullable|string|max:100',
                'ciudad' => 'nullable|string|max:100',
            ]);

            // Verificar si ya existe una empresa con el mismo nombre
            $empresaExistente = Empresa::where('nombre_empresa', $validated['nombre_empresa'])->first();
            
            if ($empresaExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una empresa con ese nombre'
                ], 422);
            }

            $empresa = new Empresa();
            $empresa->nombre_empresa = $validated['nombre_empresa'];
            $empresa->nombre_responsable = $validated['nombre_responsable'] ?? null;
            $empresa->nombre_gerente = $validated['nombre_gerente'] ?? null;
            $empresa->telefono = $validated['telefono'] ?? null;
            $empresa->email = $validated['email'] ?? null;
            $empresa->direccion = $validated['direccion'] ?? null;
            $empresa->nit = $validated['nit'] ?? null;
            $empresa->pais = $validated['pais'] ?? null;
            $empresa->ciudad = $validated['ciudad'] ?? null;
            $empresa->es_activo = 1;
            $empresa->save();

            return response()->json([
                'success' => true,
                'message' => 'Empresa creada exitosamente',
                'data' => [
                    'id_empresa' => $empresa->id_empresa,
                    'nombre_empresa' => $empresa->nombre_empresa,
                    'nombre_responsable' => $empresa->nombre_responsable,
                    'nombre_gerente' => $empresa->nombre_gerente
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la empresa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear empresa automáticamente desde check_empresa
     * Replica la lógica del sistema antiguo
     */
    private function crearEmpresaAutomatica($nombreEmpresa, $usuarioId)
    {
        try {
            // Verificar si ya existe
            $empresaExistente = Empresa::where('nombre_empresa', $nombreEmpresa)->first();
            if ($empresaExistente) {
                return $empresaExistente->id_empresa;
            }

            // Generar usuario a partir del nombre de la empresa
            // Solo alfanumérico, minúsculas, máximo 10 caracteres
            $usuario = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombreEmpresa));
            $usuario = substr($usuario, 0, 10);

            // Generar contraseña aleatoria (sistema antiguo)
            $password = $this->generarContrasena();

            // Crear la empresa con valores por defecto
            $empresa = new Empresa();
            $empresa->nombre_empresa = $nombreEmpresa;
            $empresa->telefono = ' ';
            $empresa->fax = ' ';
            $empresa->email = ' ';
            $empresa->direccion = ' ';
            $empresa->nit = ' ';
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
            $empresa->pais = '123'; // Valor por defecto (sistema antiguo)
            $empresa->ciudad = '540852'; // Valor por defecto (sistema antiguo)
            $empresa->usuario = $usuario;
            $empresa->pass = $password;
            $empresa->id_usuario_creacion = $usuarioId;
            $empresa->fecha_creacion = now();
            $empresa->fecha_actualizacion_empresa = now();
            $empresa->remember_token = '';
            $empresa->es_activo = 1;
            $empresa->save();

            return $empresa->id_empresa;
        } catch (\Exception $e) {
            throw new \Exception('Error al crear empresa automática: ' . $e->getMessage());
        }
    }

    /**
     * Resolver empresa desde check_empresa
     * Si check_empresa = 'A' → es empresa existente (usar id_empresa)
     * Si check_empresa != 'A' → es empresa nueva (crear automáticamente)
     */
    public function resolverEmpresa($checkEmpresa, $idEmpresa, $usuarioId)
    {
        // Normalizar valor entrante desde el frontend (puede llegar como string u objeto)
        // 1) Si es string y marca empresa existente
        if (is_string($checkEmpresa) && strtoupper($checkEmpresa) === 'A') {
            return $idEmpresa;
        }

        // 2) Si llega como arreglo/objeto (por ejemplo, item completo de VCombobox)
        if (is_array($checkEmpresa)) {
            $nombre = $checkEmpresa['nombre_empresa']
                ?? $checkEmpresa['label']
                ?? $checkEmpresa['text']
                ?? null;

            if (is_string($nombre) && $nombre !== '') {
                return $this->crearEmpresaAutomatica($nombre, $usuarioId);
            }

            throw new \InvalidArgumentException('Parámetro check_empresa inválido: se esperaba nombre de empresa.');
        }

        // 3) Si es string distinto de 'A', se asume nombre de nueva empresa
        if (is_string($checkEmpresa)) {
            return $this->crearEmpresaAutomatica($checkEmpresa, $usuarioId);
        }

        // 4) Cualquier otro tipo no es válido
        throw new \InvalidArgumentException('Parámetro check_empresa con tipo no soportado.');
    }

    /**
     * Generar contraseña aleatoria (sistema antiguo)
     */
    private function generarContrasena()
    {
        return \Illuminate\Support\Str::random(48);
    }

    /**
     * Obtener pabellones de una feria específica
     */
    public function getPabellonesByFeria($idFeria)
    {
        try {
            $pabellones = Pabellon::where('feria', $idFeria)
                ->orderBy('nombre_pabellon', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $pabellones
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los pabellones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar únicamente la empresa de un contrato
     */
    public function actualizarEmpresa($id, Request $request)
    {
        $request->validate([
            'check_empresa' => 'required',
            'id_empresa' => 'nullable|integer',
        ]);

        try {
            $contrato = Contrato::findOrFail($id);
            // Resolver empresa con la misma lógica que reserva
            $usuarioId = Auth::user()->id_usuario ?? Auth::id();
            $idEmpresaNueva = $this->resolverEmpresa($request->check_empresa, $request->id_empresa, $usuarioId);

            if ((int)$contrato->id_empresa === (int)$idEmpresaNueva) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay cambios en la empresa del contrato.'
                ], 422);
            }

            $contrato->id_empresa = $idEmpresaNueva;
            $contrato->save();
            
            // Disparar evento de tiempo real (empresa actualizada)
            ContratoActualizado::dispatch(
                $contrato->id_feria,
                $contrato->id_contrato,
                'update',
                ['id_empresa' => $idEmpresaNueva]
            );

            $empresa = Empresa::find($idEmpresaNueva);

            return response()->json([
                'success' => true,
                'message' => 'Empresa actualizada correctamente',
                'data' => [
                    'empresa' => [
                        'id' => $empresa->id_empresa,
                        'nombre' => $empresa->nombre_empresa,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la empresa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar stands del contrato y recalcular totales
     */
    public function actualizarStands($id, Request $request)
    {
        $request->validate([
            'id_pabellon' => 'required|integer',
            'stands' => 'required|array|min:1|max:12',
            'stands.*' => 'integer',
            'precio_unit' => 'required|numeric|min:0.01',
            'tipo_desc' => 'nullable|string',
            'porcentaje_desc' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            $contrato = Contrato::findOrFail($id);

            // Validación: pabellón no debe cambiar
            // Obtener pabellón original del contrato (a partir de los stands actuales)
            $idsStandsOriginales = [];
            for ($i = 1; $i <= 12; $i++) {
                $field = "stand_{$i}";
                $val = (int)($contrato->$field ?? 0);
                if ($val > 0) $idsStandsOriginales[] = $val;
            }
            $pabellonOriginal = null;
            if (!empty($idsStandsOriginales)) {
                $primerStandOriginal = Stand::whereIn('id_stand', $idsStandsOriginales)->first();
                $pabellonOriginal = $primerStandOriginal ? (int)$primerStandOriginal->id_pabellon : null;
            }

            // Determinar pabellón de los stands seleccionados y validar que todos pertenezcan al mismo
            $standsSeleccionados = array_values($request->stands);
            sort($standsSeleccionados);
            $pabellonesSeleccion = Stand::whereIn('id_stand', $standsSeleccionados)
                ->select('id_pabellon')
                ->distinct()
                ->pluck('id_pabellon')
                ->toArray();
            if (count($pabellonesSeleccion) !== 1) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Todos los stands seleccionados deben pertenecer al mismo pabellón.'
                ], 422);
            }
            $pabellonSeleccionado = (int)$pabellonesSeleccion[0];
            if ($pabellonOriginal !== null && $pabellonSeleccionado !== $pabellonOriginal) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se permite cambiar de pabellón en la edición del contrato.'
                ], 422);
            }

            // Validación de ocupación: ningún stand seleccionado debe estar ocupado por otro contrato
            $conflictos = Ocupacion::whereIn('id_stand', $standsSeleccionados)
                ->where('id_feria', $contrato->id_feria)
                ->where('id_contrato', '!=', $contrato->id_contrato)
                ->exists();
            if ($conflictos) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Alguno de los stands seleccionados está ocupado por otro contrato. Operación cancelada.'
                ], 422);
            }

            // Asignar stands
            $stands = $standsSeleccionados; // ya ordenados por sort()
            $totalStands = count($stands);
            for ($i=1; $i<=12; $i++) {
                $campo = "stand_{$i}";
                $contrato->$campo = $stands[$i-1] ?? 0;
            }
            $contrato->total_stands = $totalStands;

            // Recalcular metraje total
            $metraje = Stand::whereIn('id_stand', $stands)->sum('area_stand');
            $contrato->metraje_total = $metraje;

            // Actualizar descuento si se envía
            if ($request->has('porcentaje_desc')) {
                $contrato->descuento = (float)$request->porcentaje_desc;
            }
            if ($request->has('tipo_desc')) {
                $contrato->tipo_desc = $request->tipo_desc ?: 'Sin Descuento';
            }

            // Recalcular totales
            $precioUnit = round((float) $request->precio_unit, 2, PHP_ROUND_HALF_UP);
            $contrato->precio_unit = $precioUnit;
            $total = round($metraje * $precioUnit, 2, PHP_ROUND_HALF_UP);
            $contrato->precio_total = $total;
            $desc = (float)($contrato->descuento ?? 0);
            if ($desc > 0) {
                $contrato->precio_total_desc = round($total * (1 - $desc/100), 2, PHP_ROUND_HALF_UP);
            } else {
                $contrato->precio_total_desc = $total;
            }

            // Recalcular credenciales/potencia/entradas según el sistema antiguo
            $cred = $this->calcularCredenciales($contrato->id_feria, $pabellonSeleccionado, $metraje);
            $contrato->nro_credenciales = $cred['credenciales'];
            $contrato->potencia = $cred['potencia'];
            $contrato->nro_entradas = $cred['entradas'];

            // No tocar: codigo_contrato, estado_reserva y otros
            $contrato->save();
            
            // Disparar evento de tiempo real (stands actualizados)
            ContratoActualizado::dispatch(
                $contrato->id_feria,
                $contrato->id_contrato,
                'update',
                [
                    'total_stands' => $contrato->total_stands,
                    'metraje_total' => $contrato->metraje_total,
                    'precio_total' => $contrato->precio_total,
                    'precio_total_desc' => $contrato->precio_total_desc,
                    'nro_credenciales' => $contrato->nro_credenciales,
                ]
            );

            // Actualizar ocupaciones: eliminar todas las actuales del contrato y recrear
            Ocupacion::where('id_contrato', $contrato->id_contrato)->delete();
            foreach ($stands as $standId) {
                Ocupacion::create([
                    'id_stand' => $standId,
                    'id_feria' => $contrato->id_feria,
                    'id_contrato' => $contrato->id_contrato,
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Stands actualizados correctamente',
                'data' => [
                    'metraje_total' => $contrato->metraje_total,
                    'precio_total' => $contrato->precio_total,
                    'precio_total_desc' => $contrato->precio_total_desc,
                    'total_stands' => $contrato->total_stands,
                    'nro_credenciales' => $contrato->nro_credenciales,
                    'potencia' => $contrato->potencia,
                    'nro_entradas' => $contrato->nro_entradas,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar los stands: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar reserva o limpiar contrato existente.
     * - Si codigo_contrato == 0: elimina ocupaciones, visitantes y el contrato.
     * - Si codigo_contrato > 0: elimina ocupaciones, libera credenciales (según feria),
     *   resetea stands y metrajes, y mantiene el registro del contrato.
     */
    public function eliminarReserva($id)
    {
        DB::beginTransaction();
        try {
            $contrato = Contrato::findOrFail($id);

            // 1) Eliminar ocupaciones actuales del contrato
            Ocupacion::where('id_contrato', $contrato->id_contrato)->delete();
            
            // Disparar evento de tiempo real (contrato eliminado)
            ContratoActualizado::dispatch(
                $contrato->id_feria,
                $contrato->id_contrato,
                'delete',
                ['id_empresa' => $contrato->id_empresa]
            );

            // Diferenciar por codigo_contrato
            $esContratoGenerado = (int)($contrato->codigo_contrato ?? 0) > 0;

            if ($esContratoGenerado) {
                // 2) Obtener código de feria para decidir liberación de credenciales
                $feria = Feria::find($contrato->id_feria);
                $codigoFeria = $feria ? ($feria->codigo_contrato ?? '') : '';

                // 3) Liberar y eliminar credenciales (sin importar tipo de feria)
                $limite = max(0, (int)($contrato->nro_credenciales ?? 0));
                if ($limite > 0) {
                    $credenciales = Credencial::where('id_empresa', $contrato->id_empresa)
                        ->where('tipo_credencial', 1)
                        ->where('estado_credencial', '>=', 1)
                        ->where('id_feria', $contrato->id_feria)
                        ->limit($limite)
                        ->get();

                    foreach ($credenciales as $credencial) {
                        $credencial->delete();
                    }
                }

                // 4) Resetear stands y métricas del contrato
                for ($i = 1; $i <= 12; $i++) {
                    $campo = "stand_{$i}";
                    $contrato->$campo = 0;
                }
                $contrato->total_stands = 0;
                $contrato->metraje_total = 0;
                $contrato->nro_credenciales = 0;
                $contrato->save();

                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Contrato anulado correctamente',
                    'data' => [ 'tipo' => 'contrato' ]
                ]);
            }

            // Lógica para reserva (codigo_contrato == 0)
            // 5) Eliminar el contrato
            $contrato->delete();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Reserva eliminada correctamente',
                'data' => [ 'tipo' => 'reserva' ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la reserva/contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener stands disponibles de un pabellón
     * Solo retorna stands que NO están asignados a contratos activos
     */
    public function getStandsByPabellon($idFeria, $idPabellon)
    {
        try {
            // Obtener todos los stands del pabellón
            $stands = Stand::where('feria', $idFeria)
                ->where('id_pabellon', $idPabellon)
                ->orderBy('numero_stand', 'asc')
                ->get();

            // Obtener IDs de stands ocupados (asignados a contratos activos)
            // Se usa el modelo para respetar el nombre real de la tabla (contrato)
            $standsOcupados = Contrato::where('id_feria', $idFeria)
                ->where('total_stands', '>', 0) // Contratos no anulados
                ->get()
                ->flatMap(function ($contrato) {
                    $idsStands = [];
                    for ($i = 1; $i <= 12; $i++) {
                        $standField = "stand_$i";
                        if ($contrato->$standField) {
                            $idsStands[] = $contrato->$standField;
                        }
                    }
                    return $idsStands;
                })
                ->unique()
                ->values()
                ->toArray();

            // Marcar stands como disponibles o ocupados
            $standsFormateados = $stands->map(function ($stand) use ($standsOcupados) {
                return [
                    'id_stand' => $stand->id_stand,
                    'numero_stand' => $stand->numero_stand,
                    'area_stand' => $stand->area_stand,
                    'sup' => $stand->sup,
                    'izq' => $stand->izq,
                    'disponible' => !in_array($stand->id_stand, $standsOcupados)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $standsFormateados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los stands: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar nueva reserva de contrato
     * Replica la lógica del sistema antiguo
     */
    public function guardarReserva(Request $request)
    {
        $request->validate([
            'id_feria' => 'required|integer|exists:eventos,id_feria',
            'check_empresa' => 'required',
            'id_empresa' => 'nullable|integer',
            'id_pabellon' => 'required|integer|exists:pabellones,id_pabellon',
            'stands' => 'required|array|min:1|max:12',
            'stands.*' => 'required|integer|distinct|exists:stands,id_stand',
            'modo_precio' => 'required|in:m2,total',
            'precio_unit' => 'nullable|numeric|min:0.01',
            'precio_total' => 'nullable|numeric|min:0.01',
            'tipo_desc' => 'nullable|string',
            'porcentaje_desc' => 'nullable|numeric|min:0|max:100',
        ]);

        $request->validate($request->modo_precio === 'total'
            ? ['precio_total' => 'required|numeric|min:0.01']
            : ['precio_unit' => 'required|numeric|min:0.01']);

        $feria = Feria::findOrFail($request->id_feria);
        $pabellon = Pabellon::query()
            ->where('id_pabellon', $request->id_pabellon)
            ->where('feria', $feria->id_feria)
            ->first();

        if (!$pabellon) {
            return response()->json([
                'success' => false,
                'message' => 'El pabellón seleccionado no pertenece a la feria indicada.',
                'errors' => ['id_pabellon' => ['Seleccione un pabellón de la feria elegida.']],
            ], 422);
        }

        DB::beginTransaction();
        try {
            $stands = Stand::query()
                ->whereIn('id_stand', $request->stands)
                ->where('feria', $feria->id_feria)
                ->where('id_pabellon', $pabellon->id_pabellon)
                ->orderBy('id_stand')
                ->lockForUpdate()
                ->get();

            if ($stands->count() !== count($request->stands)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Uno o más stands no pertenecen al pabellón y feria seleccionados.',
                    'errors' => ['stands' => ['Revise la selección de stands e inténtelo nuevamente.']],
                ], 422);
            }

            $metrajeTotal = (float) $stands->sum('area_stand');
            if (!is_finite($metrajeTotal) || $metrajeTotal <= 0) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Los stands seleccionados no tienen un metraje válido.',
                    'errors' => ['stands' => ['Seleccione stands con un área mayor a cero.']],
                ], 422);
            }

            $usuarioId = Auth::user()->id_usuario ?? Auth::id();

            // 1. Resolver empresa (crear o recuperar)
            $idEmpresa = $this->resolverEmpresa(
                $request->check_empresa,
                $request->id_empresa,
                $usuarioId
            );

            $empresa = Empresa::findOrFail($idEmpresa);

            // 2. Calcular el precio base, redondeando cada importe a centavos.
            if ($request->modo_precio === 'total') {
                $precioTotal = round((float) $request->precio_total, 2, PHP_ROUND_HALF_UP);
                $precioUnitario = round($precioTotal / $metrajeTotal, 2, PHP_ROUND_HALF_UP);
            } else {
                $precioUnitario = round((float) $request->precio_unit, 2, PHP_ROUND_HALF_UP);
                $precioTotal = round($metrajeTotal * $precioUnitario, 2, PHP_ROUND_HALF_UP);
            }

            $porcentajeDesc = $request->porcentaje_desc ?? 0;
            $importeDescuento = round(($precioTotal * $porcentajeDesc) / 100, 2, PHP_ROUND_HALF_UP);
            $precioTotalFinal = round($precioTotal - $importeDescuento, 2, PHP_ROUND_HALF_UP);

            // 4. Determinar tipo de descuento
            $tipoDesc = $request->tipo_desc ?? 'Sin Descuento';

            // 5. Calcular credenciales según límites del pabellón
            $credenciales = $this->calcularCredenciales(
                $request->id_feria,
                $request->id_pabellon,
                $metrajeTotal
            );

            // 6. Generar clave de acceso (password)
            $clave = $this->generarContrasena();

            // 7. Calcular tiempo de reserva (5 días hábiles)
            $fechaCreacion = now();
            $tiempoReserva = 5; // días
            $fechaFinReserva = $this->calcularFechaFinReserva($fechaCreacion, $tiempoReserva);

            // 8. Procesar stands seleccionados (lógica del sistema antiguo)
            $totalStands = count($request->stands);

            // PROTECCIÓN DE CONCURRENCIA: Verificar ocupación de stands con lock
            foreach ($request->stands as $standId) {
                $ocupado = \App\Models\Ocupacion::where('id_stand', $standId)
                    ->where('id_feria', $request->id_feria)
                    ->lockForUpdate()
                    ->first();
                if ($ocupado) {
                    throw new \Exception('El stand ya fue reservado por otro usuario.', 409);
                }
            }

            // 9. Crear el contrato
            $contrato = new Contrato();
            $contrato->id_empresa = $idEmpresa;
            $contrato->id_feria = $request->id_feria;
            $contrato->codigo_contrato = 0; // Se genera más adelante al formalizar contrato
            $contrato->total_stands = $totalStands;
            // Asignar stands (stand_1 a stand_12) - misma lógica del sistema antiguo
            $contrato->stand_1 = $request->stands[0] ?? 0;
            $contrato->stand_2 = ($totalStands > 1) ? $request->stands[1] : 0;
            $contrato->stand_3 = ($totalStands > 2) ? $request->stands[2] : 0;
            $contrato->stand_4 = ($totalStands > 3) ? $request->stands[3] : 0;
            $contrato->stand_5 = ($totalStands > 4) ? $request->stands[4] : 0;
            $contrato->stand_6 = ($totalStands > 5) ? $request->stands[5] : 0;
            $contrato->stand_7 = ($totalStands > 6) ? $request->stands[6] : 0;
            $contrato->stand_8 = ($totalStands > 7) ? $request->stands[7] : 0;
            $contrato->stand_9 = ($totalStands > 8) ? $request->stands[8] : 0;
            $contrato->stand_10 = ($totalStands > 9) ? $request->stands[9] : 0;
            $contrato->stand_11 = ($totalStands > 10) ? $request->stands[10] : 0;
            $contrato->stand_12 = ($totalStands > 11) ? $request->stands[11] : 0;

            $contrato->metraje_total = $metrajeTotal;
            $contrato->precio_unit = $precioUnitario;
            $contrato->descuento = $porcentajeDesc;
            $contrato->precio_total = $precioTotal;
            $contrato->precio_total_desc = $precioTotalFinal;
            $contrato->tipo_desc = $tipoDesc;
            // Campos económicos obligatorios
            $contrato->monto_inicial = 0;
            $contrato->porcentaje_inicial = 0;
            $contrato->fecha_inicial = '1900-01-01';
            $contrato->monto_final = 0;
            $contrato->porcentaje_final = 0;
            $contrato->fecha_final = '1900-01-01';
            // Campos de tipo TEXT obligatorios (no pueden ser null, deben ser cadena vacía)
            $contrato->tipo_pago = '';
            $contrato->nro_cheque = '';
            $contrato->banco = '';
            $contrato->productos = '';
            $contrato->perfil_visitante = '';
            $contrato->pais_principal = '';
            $contrato->marca_principal = '';
            $contrato->paises_secundarios = '';
            $contrato->marcas_secundarios = '';
            $contrato->observaciones = '';
            // Campos numéricos obligatorios
            $contrato->habilitado_feria = 0;
            $contrato->envio_mail = 0;
            $contrato->fecha_realizacion = '1900-01-01';
            $contrato->id_carta = 0;
            $contrato->entrega = 0;
            $contrato->pago = 0;
            // Campos que SÍ pueden ser NULL (según estructura DB)
            $contrato->id_usuario_contrato = null;
            $contrato->medio_comunicacion = null;
            $contrato->como_entero = null;
            $contrato->tipo_expositor = null;
            $contrato->aprobacion = 0;
            // Credenciales
            $contrato->nro_credenciales = $credenciales['credenciales'];
            $contrato->potencia = $credenciales['potencia'];
            $contrato->nro_entradas = $credenciales['entradas'];
            // Datos de reserva
            $contrato->clave = $clave;
            $contrato->id_usuario_reserva = $usuarioId;
            $contrato->fecha_creacion_reserva = $fechaCreacion;
            $contrato->tiempo_reserva = $tiempoReserva;
            $contrato->fecha_fin_reserva = $fechaFinReserva;
            $contrato->estado_reserva = 2; // Reservado
            $contrato->save();

            // Disparar evento de tiempo real (nueva reserva)
            ContratoActualizado::dispatch(
                $contrato->id_feria,
                $contrato->id_contrato,
                'create',
                [
                    'id_empresa' => $contrato->id_empresa,
                    'codigo_contrato' => $contrato->codigo_contrato,
                    'total_stands' => $contrato->total_stands,
                    'metraje_total' => $contrato->metraje_total,
                    'precio_total' => $contrato->precio_total,
                    'estado_reserva' => $contrato->estado_reserva,
                    'stands' => $request->stands,
                ]
            );

            // 10. Registrar ocupaciones de stands
            foreach ($request->stands as $standId) {
                Ocupacion::create([
                    'id_stand' => $standId,
                    'id_feria' => $request->id_feria,
                    'id_contrato' => $contrato->id_contrato,
                ]);
            }

            DB::commit();

            $standsNombres = Stand::query()
                ->whereIn('id_stand', $request->stands)
                ->pluck('numero_stand')
                ->all();

            event(new \App\Events\ReservaCreada([
                'id' => $contrato->id_contrato,
                'empresa' => $empresa->nombre_empresa ?? 'Empresa',
                'feria' => $feria->nombre_feria ?? 'Feria',
                'fecha' => now()->format('d/m/Y'),
                'stand' => implode(', ', $standsNombres),
                'superficie' => $contrato->metraje_total,
                'precio_unitario' => $contrato->precio_unit,
                'descuento' => $contrato->descuento,
                'precio_total' => $contrato->precio_total_desc,
                'usuario' => Auth::user()->nombre_usuario ?? 'Usuario del sistema',
                'estado' => 'Reservado',
                'url' => $request->getSchemeAndHttpHost() . '/contrato/llenado/' . $contrato->id_contrato,
            ]));

            return response()->json([
                'success' => true,
                'contrato_id' => $contrato->id_contrato
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            // Si es conflicto de concurrencia, retornar 409
            if ($e->getCode() == 409) {
                return response()->json([
                    'success' => false,
                    'message' => 'El stand ya fue reservado por otro usuario. Actualice la lista.'
                ], 409);
            }
            // Si es error de restricción única (SQLSTATE 23000)
            if (method_exists($e, 'getCode') && $e->getCode() == '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'El stand ya fue reservado por otro usuario. Actualice la lista.'
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la reserva: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular credenciales según límites del pabellón
     */
    private function calcularCredenciales($idFeria, $idPabellon, $metraje)
    {
        // Buscar límite de credenciales
        $limite = LimiteCredencial::where('id_feria', $idFeria)
            ->where('tipo_area', $idPabellon)
            ->where('limite_sup', '>=', $metraje)
            ->orderBy('limite_sup', 'asc')
            ->first();

        if ($limite) {
            // Usar valores directos del límite
            return [
                'credenciales' => $limite->cant_credenciales,
                'potencia' => $limite->cant_credenciales, // Mismo valor
                'entradas' => $limite->cant_credenciales, // Mismo valor
            ];
        }

        // Si no existe límite exacto, aplicar regla de tres
        // Buscar el límite más cercano
        $limiteCercano = LimiteCredencial::where('id_feria', $idFeria)
            ->where('tipo_area', $idPabellon)
            ->orderBy('limite_sup', 'desc')
            ->first();

        if ($limiteCercano) {
            $factor = $metraje / $limiteCercano->limite_sup;
            $credenciales = (int) round($limiteCercano->cant_credenciales * $factor, 0, PHP_ROUND_HALF_UP);
            
            return [
                'credenciales' => $credenciales,
                'potencia' => $credenciales,
                'entradas' => $credenciales,
            ];
        }

        // Por defecto, si no hay límites configurados
        return [
            'credenciales' => 2,
            'potencia' => 2,
            'entradas' => 2,
        ];
    }

    /**
     * Calcular fecha fin de reserva (solo días hábiles - lunes a viernes)
     */
    private function calcularFechaFinReserva($fechaInicio, $diasHabiles)
    {
        $fecha = clone $fechaInicio;
        $diasContados = 0;

        while ($diasContados < $diasHabiles) {
            $fecha->addDay();
            
            // Solo contar días de lunes (1) a viernes (5)
            if ($fecha->dayOfWeek >= 1 && $fecha->dayOfWeek <= 5) {
                $diasContados++;
            }
        }

        return $fecha;
    }

    /**
     * Guardar el llenado del contrato (replicando lógica del sistema antiguo)
     */
    public function guardarLlenado($id, Request $request)
    {
        try {
            $idFeria = (int) Contrato::query()->whereKey($id)->value('id_feria');
            $request->validate([
                'tipo_credenciales' => $idFeria >= 21
                    ? ['required', 'in:0,1']
                    : ['nullable', 'in:0,1'],
                'email' => ['nullable', 'email', 'max:255'],
                'email_representante' => ['nullable', 'email', 'max:255'],
                'modo_precio' => ['nullable', 'in:m2,total'],
                'precio_unit' => ['nullable', 'numeric', 'min:0.01'],
                'precio_total' => ['nullable', 'numeric', 'min:0.01'],
                'descuento' => ['required', 'numeric', 'min:0', 'max:100'],
            ], [
                'tipo_credenciales.required' => 'Debe seleccionar el tipo de credencial (Física o Digital).',
                'tipo_credenciales.in' => 'El tipo de credencial debe ser Física o Digital.',
            ]);

            $modoPrecio = $request->input('modo_precio', 'm2');
            $request->validate($modoPrecio === 'total'
                ? ['precio_total' => ['required', 'numeric', 'min:0.01']]
                : ['precio_unit' => ['required', 'numeric', 'min:0.01']]);

            DB::beginTransaction();

            // 1. Obtener contrato y empresa
            $contrato = Contrato::findOrFail($id);
            $empresa = Empresa::findOrFail($contrato->id_empresa);

            $metrajeTotal = (float) $contrato->metraje_total;
            if (!is_finite($metrajeTotal) || $metrajeTotal <= 0) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El contrato no tiene un metraje válido.',
                    'errors' => ['metraje_total' => ['El metraje debe provenir de los stands reservados.']],
                ], 422);
            }

            // 2. Calcular montos usando el metraje guardado en la reserva
            if ($modoPrecio === 'total') {
                $total = round((float) $request->precio_total, 2, PHP_ROUND_HALF_UP);
                $precioUnitario = round($total / $metrajeTotal, 2, PHP_ROUND_HALF_UP);
            } else {
                $precioUnitario = round((float) $request->precio_unit, 2, PHP_ROUND_HALF_UP);
                $total = round($precioUnitario * $metrajeTotal, 2, PHP_ROUND_HALF_UP);
            }
            $descuento = round(($total * $request->descuento) / 100, 2, PHP_ROUND_HALF_UP);
            $total_pagar = round($total - $descuento, 2, PHP_ROUND_HALF_UP);
            
            // Guardar datos antes para auditoría
            $datosAntes = $empresa->toArray();

            // 3. Actualizar datos del contrato (evitar null en columnas TEXT)
            $contrato->productos = $request->productos ?? '';
            $contrato->perfil_visitante = $request->perfil_visitante ?? '';

            // Procesar marcas nacionales
            $v1 = "";
            if ($request->marca_principal > 0 && isset($request->mn)) {
                for ($i = 0; $i < $request->marca_principal; $i++) {
                    if (!isset($request->mn[$i])) continue;
                    if ($v1 != '') $v1 .= ";";
                    $v1 .= $request->mn[$i];
                }
            }
            $contrato->marca_principal = $v1;

            // Procesar marcas internacionales
            $v2 = "";
            if ($request->pais_principal > 0 && isset($request->pp) && isset($request->pais_pp)) {
                for ($i = 0; $i < $request->pais_principal; $i++) {
                    if (!isset($request->pp[$i]) || !isset($request->pais_pp[$i])) continue;
                    if ($v2 != '') $v2 .= ";";
                    $v2 .= $request->pp[$i] . ";" . $request->pais_pp[$i];
                }
            }
            $contrato->pais_principal = $v2;

            // Procesar marcas multinacionales
            $v3 = "";
            if ($request->marcas_secundarios > 0 && isset($request->mu) && isset($request->pais_mu)) {
                for ($i = 0; $i < $request->marcas_secundarios; $i++) {
                    if (!isset($request->mu[$i]) || !isset($request->pais_mu[$i])) continue;
                    if ($v3 != '') $v3 .= ";";
                    $v3 .= $request->mu[$i] . ";" . $request->pais_mu[$i];
                }
            }
            $contrato->marcas_secundarios = $v3;

            $contrato->observaciones = $request->observaciones ?? '';
            $contrato->precio_unit = $precioUnitario;
            $contrato->descuento = $request->descuento;
            $contrato->precio_total = $total;
            $contrato->precio_total_desc = $total_pagar;
            $contrato->tipo_desc = $request->tipo_desc ?? 'Sin Descuento';
            $contrato->monto_inicial = $request->monto_inicial ?? 0;

            // Calcular monto final
            if ($request->descuento == 0) {
                $contrato->monto_final = $contrato->precio_total - $contrato->monto_inicial;
            } else {
                $contrato->monto_final = $total_pagar - $contrato->monto_inicial;
            }

            // Calcular porcentajes
            if ($contrato->monto_inicial > 0) {
                if ($request->descuento == 0) {
                    $contrato->porcentaje_inicial = round(100 * $contrato->monto_inicial / $total, 2);
                    $contrato->porcentaje_final = round(100 - $contrato->porcentaje_inicial, 2);
                } else {
                    $contrato->porcentaje_inicial = round(100 * $contrato->monto_inicial / $total_pagar, 2);
                    $contrato->porcentaje_final = round(100 - $contrato->porcentaje_inicial, 2);
                }
            } else {
                $contrato->porcentaje_inicial = 0;
                $contrato->porcentaje_final = 100;
            }

            $contrato->fecha_inicial = $request->fecha_inicial;
            $contrato->fecha_final = $request->fecha_final;
            $contrato->medio_comunicacion = $request->medio_comunicacion ?? '0';
            $contrato->como_entero = $request->como_entero ?? '0';
            $contrato->tipo_expositor = $request->tipo_expositor ?? '0';
            $contrato->tipo_credenciales = (int) $contrato->id_feria >= 21
                ? $request->tipo_credenciales
                : 0;
            
            $contrato->save();

            // 4. Actualizar datos de la empresa
            $pendientes = '';
            
            $empresa->nombre_empresa = $request->nombre_empresa;
            
            if ($request->direccion == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'direccion';
            $empresa->direccion = $request->direccion;
            
            if ($request->telefono == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'telefono';
            $empresa->telefono = $request->telefono;
            
            if ($request->nit == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'nit';
            $empresa->nit = $request->nit;
            
            if ($request->email == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'email';
            $empresa->email = $request->email ?? '';
            
            if ($request->web == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'pagina web';
            $empresa->web = $request->web;
            
            if ($request->aniversario == '1900-01-01' || $request->aniversario == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'fecha aniversario';
            $empresa->aniversario = $request->aniversario;
            
            if ($request->nr_escritura == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'numero de escritura';
            $empresa->nr_escritura = $request->nr_escritura;
            
            if ($request->fecha_nr_escritura == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'fecha de escritura';
            $empresa->fecha_nr_escritura = $request->fecha_nr_escritura;
            
            if ($request->matricula == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'matricula';
            $empresa->matricula = $request->matricula;
            
            if ($request->nr_poder == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'nro poder';
            $empresa->nr_poder = $request->nr_poder;
            
            if ($request->nr_notaria == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'nro notaria';
            $empresa->nr_notaria = $request->nr_notaria;
            
            if ($request->fecha_nr_poder == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'fecha nr_poder';
            $empresa->fecha_nr_poder = $request->fecha_nr_poder;
            
            if ($request->distrito == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'Distrito';
            $empresa->distrito = $request->distrito;
            
            if ($request->cluster == '0' || $request->cluster == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'categoria';
            $empresa->cluster = $request->cluster;
            
            if ($request->pais_id == '0' || $request->pais_id == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'pais';
            $empresa->pais = $request->pais_id;
            
            if ($request->ciudad_id == '0' || $request->ciudad_id == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'ciudad';
            $empresa->ciudad = $request->ciudad_id;
            
            if ($request->rubro == '0' || $request->rubro == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'rubro';
            $empresa->rubro = $request->rubro;
            
            if ($request->nombre_gerente == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'nombre del representante';
            $empresa->nombre_gerente = $request->nombre_gerente;
            
            if ($request->ci_gerente == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'carnet de identidad del representante';
            $empresa->ci_gerente = $request->ci_gerente;
            
            $empresa->exp_ci_gerente = $request->exp_ci_gerente;
            
            if ($request->fono_gerente == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'telefono del representante';
            $empresa->fono_gerente = $request->fono_gerente;
            
            if ($request->cargo_gerente == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'cargo del representante';
            $empresa->cargo_gerente = $request->cargo_gerente;
            
            if ($request->nombre_responsable == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'nombre del responsable';
            $empresa->nombre_responsable = $request->nombre_responsable;
            
            if ($request->telefono_responsable == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'telefono del responsable';
            $empresa->telefono_responsable = $request->telefono_responsable;
            
            if ($request->email_responsable == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'email del responsable';
            $empresa->email_representante = $request->email_representante ?? '';
            
            if ($request->actividad_principal == '0' || $request->actividad_principal == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'actividad principal';
            $empresa->id_tipo_representante = $request->actividad_principal . "00";
            
            if ($request->otro_rubro == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'otros rubros';
            $empresa->otro_rubro = $request->otro_rubro;
            
            if ($request->subrubro == '0' || $request->subrubro == '')
                $pendientes .= ($pendientes == '' ? "" : ';') . 'subrubro';
            $empresa->subrubro = $request->subrubro;
            
            $empresa->pendiente_llenado = $pendientes;
            if ($this->empresaTieneCambios($datosAntes, $empresa->toArray())) {
                $empresa->fecha_actualizacion_empresa = now();
                $empresa->id_usuario_actualizacion = Auth::user()->id_usuario ?? null;
                $empresa->save();

                // 5. Guardar auditoría
                Backedempresa::create([
                    'id_empresa' => $empresa->id_empresa,
                    'modificado_por' => Auth::user()->nombre_usuario ?? 'Sistema',
                    'datos_antes' => json_encode($datosAntes),
                    'datos_despues' => json_encode($empresa->toArray()),
                    'fecha' => now(),
                ]);
            }

            DB::commit();

            event(new \App\Events\ActualizacionDatos([
                'empresa' => $empresa->nombre_empresa ?? 'Empresa',
                'feria' => optional($contrato->feria)->nombre_feria ?? 'Feria',
                'contrato' => (string) $contrato->id_contrato,
                'fecha' => now()->format('d/m/Y H:i'),
                'usuario' => Auth::user()->nombre_usuario ?? 'Usuario del sistema',
                'url' => $request->getSchemeAndHttpHost() . '/contrato/llenado/' . $contrato->id_contrato,
            ]));
            
            return response()->json([
                'success' => true,
                'message' => 'Contrato guardado exitosamente',
                'data' => [
                    'contrato' => $contrato,
                    'empresa' => $empresa,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function empresaTieneCambios(array $datosAntes, array $datosDespues): bool
    {
        $camposSeguimiento = ['fecha_actualizacion_empresa', 'id_usuario_actualizacion', 'updated_at'];
        $campos = array_unique(array_merge(array_keys($datosAntes), array_keys($datosDespues)));

        foreach ($campos as $campo) {
            if (in_array($campo, $camposSeguimiento, true)) {
                continue;
            }

            if (!Empresa::valoresEquivalentes($datosAntes[$campo] ?? null, $datosDespues[$campo] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Datos para la vista de cambiar empresa/stands (JSON)
     * Replica la carga del método antiguo `cambiar($id_contrato)` pero en formato API.
     */
    public function getDatosCambio($id)
    {
        try {
            $contrato = Contrato::findOrFail($id);

            // Si ya tiene código de contrato generado, no permitir cambios
            if ($contrato->codigo_contrato > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El contrato ya está generado. No se permite cambiar empresa/stands.'
                ], 422);
            }

            // Empresa actual
            $empresaActual = Empresa::findOrFail($contrato->id_empresa);

            // Otras empresas para el selector
            $empresas = Empresa::select('id_empresa', 'nombre_empresa')
                ->where('id_empresa', '!=', $contrato->id_empresa)
                ->where('es_activo', 1)
                ->orderBy('nombre_empresa', 'asc')
                ->get();

            // Pabellones de la feria del contrato
            $pabellones = Pabellon::where('feria', $contrato->id_feria)
                ->orderBy('nombre_pabellon', 'asc')
                ->get();

            // Stands asignados (hasta 12)
            $idsStands = [];
            for ($i = 1; $i <= 12; $i++) {
                $field = "stand_{$i}";
                $val = (int)($contrato->$field ?? 0);
                if ($val > 0) $idsStands[] = $val;
            }

            $standsAsignados = [];
            $pabellonActual = null;
            if (!empty($idsStands)) {
                $stands = Stand::whereIn('id_stand', $idsStands)->get();
                $standsAsignados = $stands->map(function ($s) {
                    return [
                        'id' => $s->id_stand,
                        'codigo' => (string)$s->numero_stand,
                        'm2' => (float)($s->area_stand ?? 0),
                        'x' => (int)($s->izq ?? 0),
                        'y' => (int)($s->sup ?? 0),
                        'w' => 40,
                        'h' => 30,
                    ];
                })->values();

                // Tomar pabellón del primer stand
                $primerStand = $stands->first();
                if ($primerStand) {
                    $pabellonActual = Pabellon::find($primerStand->id_pabellon);
                }
            }

            // Feria
            $feria = Feria::find($contrato->id_feria);

            return response()->json([
                'success' => true,
                'data' => [
                    'contrato' => [
                        'id_contrato' => $contrato->id_contrato,
                        'id_feria' => $contrato->id_feria,
                        'id_empresa' => $contrato->id_empresa,
                        'codigo_contrato' => $contrato->codigo_contrato,
                        'metraje_total' => $contrato->metraje_total ?? 0,
                        'precio_unit' => $contrato->precio_unit ?? 0,
                        'descuento' => $contrato->descuento ?? 0,
                        'tipo_desc' => $contrato->tipo_desc ?? 'Sin Descuento',
                    ],
                    'empresa' => [
                        'id' => $empresaActual->id_empresa,
                        'nombre' => $empresaActual->nombre_empresa,
                    ],
                    'feria' => $feria ? [
                        'id' => $feria->id_feria,
                        'nombre' => $feria->nombre_feria,
                    ] : null,
                    'pabellon' => $pabellonActual ? [
                        'id_pabellon' => $pabellonActual->id_pabellon,
                        'nombre_pabellon' => $pabellonActual->nombre_pabellon,
                        'feria' => $pabellonActual->feria,
                        'mapa_url' => $pabellonActual->mapa_url,
                    ] : null,
                    'stands_asignados' => $standsAsignados,
                    'pabellones' => $pabellones,
                    'empresas' => $empresas,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de cambio: ' . $e->getMessage()
            ], 500);
        }
    }

    private function validarDatosObligatoriosParaGenerar(Contrato $contrato, Empresa $empresa)
    {
        $actividadPrincipal = (string) ($empresa->id_tipo_representante ?? '');
        $email = trim((string) $empresa->email);

        if (preg_match('/^[1-3]00$/', $actividadPrincipal)) {
            $actividadPrincipal = substr($actividadPrincipal, 0, -2);
        }

        $datos = [
            'nombre_empresa' => $empresa->nombre_empresa,
            'direccion' => $empresa->direccion,
            'nit' => $empresa->nit,
            'telefono' => $empresa->telefono,
            'email' => $email !== '' ? $email : null,
            'pais_id' => $empresa->pais,
            'ciudad_id' => $empresa->ciudad,
            'cluster' => $empresa->cluster,
            'actividad_principal' => $actividadPrincipal,
            'rubro' => $empresa->rubro,
            'subrubro' => $empresa->subrubro,
            'otro_rubro' => $empresa->otro_rubro,
            'nombre_gerente' => $empresa->nombre_gerente,
            'ci_gerente' => $empresa->ci_gerente,
            'exp_ci_gerente' => $empresa->exp_ci_gerente,
            'fono_gerente' => $empresa->fono_gerente,
            'cargo_gerente' => $empresa->cargo_gerente,
            'nombre_responsable' => $empresa->nombre_responsable,
            'telefono_responsable' => $empresa->telefono_responsable,
            'medio_comunicacion' => $contrato->medio_comunicacion,
            'como_entero' => $contrato->como_entero,
            'tipo_expositor' => $contrato->tipo_expositor,
            'tipo_credenciales' => $contrato->tipo_credenciales,
            'productos' => $contrato->productos,
        ];

        $formRequest = new StoreContratoRequest();

        return Validator::make(
            $datos,
            $formRequest->rules(),
            $formRequest->messages(),
            $formRequest->attributes()
        );
    }

    /**
     * Generar código de contrato y actualizar estado
     */
    public function generarContrato(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            // Bloquear contrato y feria para evitar generar un código dos veces en paralelo.
            $contrato = Contrato::where('id_contrato', $id)->lockForUpdate()->first();

            if (!$contrato) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Contrato no encontrado'
                ], 404);
            }

            // 2. Verificar que codigo_contrato sea 0
            if ($contrato->codigo_contrato != 0) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El contrato ya tiene un código asignado: ' . $contrato->codigo_contrato,
                    'data' => [
                        'codigo_contrato' => $contrato->codigo_contrato
                    ]
                ], 400);
            }

            $empresa = Empresa::where('id_empresa', $contrato->id_empresa)->lockForUpdate()->first();
            if (!$empresa) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede generar el contrato porque la empresa asociada no existe.',
                ], 422);
            }

            $validacion = $this->validarDatosObligatoriosParaGenerar($contrato, $empresa);
            if ($validacion->fails()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Complete los datos obligatorios antes de generar el contrato.',
                    'errors' => $validacion->errors(),
                ], 422);
            }

            $precioUnitario = (float) $contrato->precio_unit;
            $descuentoContrato = (float) ($contrato->descuento ?? 0);
            if (
                !is_finite($precioUnitario) ||
                $precioUnitario <= 0 ||
                !is_finite($descuentoContrato) ||
                $descuentoContrato < 0 ||
                $descuentoContrato > 100
            ) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El contrato no tiene una configuración económica válida.',
                    'errors' => [
                        'precio_unit' => ['El precio unitario debe ser mayor a cero.'],
                        'descuento' => ['El descuento debe estar entre cero y cien por ciento.'],
                    ],
                ], 422);
            }

            $feria = Feria::where('id_feria', $contrato->id_feria)->lockForUpdate()->first();
            if (!$feria) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede generar el contrato porque la feria asociada no existe.',
                    'errors' => ['id_feria' => ['La feria asociada no es válida.']],
                ], 422);
            }

            $standsAsignados = collect(range(1, 12))
                ->map(fn ($indice) => $contrato->{'stand_' . $indice} ?? null)
                ->filter(fn ($standId) => (int) $standId > 0)
                ->values();
            $standIds = $standsAsignados->unique()->values();

            if (
                (int) $contrato->estado_reserva !== 2 ||
                $standsAsignados->isEmpty() ||
                $standIds->count() !== (int) $contrato->total_stands ||
                $standsAsignados->count() !== $standIds->count()
            ) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'La reserva no está vigente o su asignación de stands es inconsistente.',
                    'errors' => ['stands' => ['Verifique el estado de la reserva y sus stands.']],
                ], 422);
            }

            $standsValidos = Stand::query()
                ->whereIn('id_stand', $standIds)
                ->where('feria', $feria->id_feria)
                ->whereHas('pabellon', fn ($query) => $query->where('feria', $feria->id_feria))
                ->lockForUpdate()
                ->get();

            if ($standsValidos->count() !== $standIds->count()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede generar el contrato porque uno o más stands no pertenecen a la feria.',
                    'errors' => ['stands' => ['Revise los stands asignados a esta reserva.']],
                ], 422);
            }

            $metrajeReal = (float) $standsValidos->sum('area_stand');
            if (
                !is_finite($metrajeReal) ||
                $metrajeReal <= 0 ||
                abs($metrajeReal - (float) $contrato->metraje_total) > 0.01
            ) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El metraje registrado no coincide con las áreas de los stands.',
                    'errors' => ['metraje_total' => ['Actualice la reserva para corregir su metraje antes de generar.']],
                ], 422);
            }

            // 3. Obtener el último código de la feria bloqueada
            $ultimoContrato = Contrato::where('id_feria', $contrato->id_feria)
                ->where('codigo_contrato', '>', 0)
                ->orderBy('codigo_contrato', 'desc')
                ->first();

            // Calcular nuevo código (igual que el sistema antiguo)
            if ($ultimoContrato && $ultimoContrato->codigo_contrato > 0) {
                $nuevoCodigo = intval($ultimoContrato->codigo_contrato) + 1;
            } else {
                $inicioValue = $feria ? $feria->inicio : 1;
                $nuevoCodigo = intval($inicioValue);
                if ($nuevoCodigo === 0) {
                    $nuevoCodigo = 1;
                }
            }

            // 4. Actualizar los campos del contrato con valores explícitos
            $contrato->codigo_contrato = $nuevoCodigo;
            $contrato->estado_reserva = 3;
            $contrato->habilitado_feria = 1;
            $contrato->fecha_realizacion = date('Y-m-d');
            $contrato->id_usuario_contrato = Auth::user()->id_usuario;
            $contrato->aprobacion = 2;

            // 5. Guardar los cambios
            $contrato->save();
            
            // Disparar evento de tiempo real (contrato generado/formalizado)
            ContratoActualizado::dispatch(
                $contrato->id_feria,
                $contrato->id_contrato,
                'update',
                [
                    'id_empresa' => $contrato->id_empresa,
                    'codigo_contrato' => $contrato->codigo_contrato,
                    'estado_reserva' => $contrato->estado_reserva,
                    'habilitado_feria' => $contrato->habilitado_feria,
                    'nro_credenciales' => $contrato->nro_credenciales,
                ]
            );

            DB::commit();

            $primerStand = $contrato->stand_1 ? Stand::find($contrato->stand_1) : null;
            $pabellon = $primerStand ? Pabellon::find($primerStand->id_pabellon) : null;
            $stands = collect(range(1, 12))
                ->map(fn ($indice) => $contrato->{'stand_' . $indice} ?? null)
                ->filter()
                ->map(fn ($standId) => Stand::find($standId)?->numero_stand)
                ->filter()
                ->implode(', ');

            event(new \App\Events\ContratoCreado([
                'id' => $contrato->id_contrato,
                'numero' => (string) $nuevoCodigo,
                'empresa' => $contrato->empresa?->nombre_empresa ?? 'Empresa',
                'feria' => $contrato->feria?->nombre_feria ?? 'Feria',
                'pabellon' => $pabellon?->nombre_pabellon ?? 'N/A',
                'stand' => $stands !== '' ? $stands : 'N/A',
                'precio_total' => $contrato->precio_total ?? 0,
                'fecha' => now()->format('d/m/Y'),
                'estado' => 'Generado',
                'usuario' => Auth::user()?->nombre_usuario ?? 'el sistema',
                'url' => url("/contratos/{$contrato->id_contrato}/imprimir"),
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Contrato generado correctamente',
                'data' => [
                    'codigo_contrato' => $nuevoCodigo,
                    'contrato' => $contrato
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exportar contratos de una feria a Excel
     */
    public function exportarContratos($idFeria)
    {
        try {
            // Aumentar memoria para exportación de archivos Excel grandes
            $memoryLimit = @ini_get('memory_limit');
            if ($memoryLimit && preg_match('/^(\d+)([kmg]?)/i', $memoryLimit, $matches)) {
                $value = (int) $matches[1];
                $unit = strtolower($matches[2] ?? '');
                $bytes = match ($unit) {
                    'k' => $value * 1024,
                    'm' => $value * 1024 * 1024,
                    'g' => $value * 1024 * 1024 * 1024,
                    default => $value,
                };

                if ($bytes < 512 * 1024 * 1024) {
                    @ini_set('memory_limit', '512M');
                }
            }

            // Verificar que la feria existe
            $feria = Feria::find($idFeria);
            
            if (!$feria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feria no encontrada',
                ], 404);
            }

            // Nombre del archivo
            $nombreArchivo = 'Lista Contratos - ' . $feria->nombre_feria . '.xlsx';

            // Usar maatwebsite/excel para exportar
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\ContratosExport($idFeria),
                $nombreArchivo
            );

        } catch (\Throwable $e) {
            Log::error('Error exportando contratos', [
                'id_feria' => $idFeria,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al exportar contratos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener el estado de ventas completo por pabellones de una feria
     * Migración completa del sistema antiguo con arquitectura optimizada
     * 
     * @param int $idFeria ID de la feria
     * @return \Illuminate\Http\JsonResponse
     */
    public function estadoVentas($idFeria)
    {
        try {
            // Validar que la feria existe
            $feria = Feria::find($idFeria);
            
            if (!$feria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feria no encontrada',
                ], 404);
            }

            // Obtener todos los pabellones de la feria con sus stands en una sola consulta optimizada
            $pabellones = Pabellon::where('feria', $idFeria)
                ->orderBy('nombre_pabellon', 'asc')
                ->get();

            // Obtener todos los stands de la feria en una sola consulta
            $standsMap = Stand::whereIn('id_pabellon', $pabellones->pluck('id_pabellon'))
                ->where('feria', $idFeria)
                ->orderByRaw('CAST(numero_stand AS UNSIGNED) ASC')
                ->get()
                ->groupBy('id_pabellon');

            // Obtener todas las ocupaciones de la feria con contratos y empresas en una sola consulta (evitar N+1)
            $ocupaciones = Ocupacion::where('id_feria', $idFeria)
                ->with(['contrato.empresa'])
                ->get()
                ->groupBy('id_stand');

            // Obtener todos los pagos aprobados de la feria agrupados por empresa
            $pagosPorEmpresa = DB::table('pago')
                ->where('id_feria', $idFeria)
                ->where('estado', 1) // Solo aprobados
                ->select('id_empresa', DB::raw('SUM(monto) as total_pagado'))
                ->groupBy('id_empresa')
                ->pluck('total_pagado', 'id_empresa');

            // Obtener todos los contratos de la feria ordenados por empresa y código
            $todosContratos = Contrato::where('id_feria', $idFeria)
                ->where('codigo_contrato', '>', 0)
                ->orderBy('id_empresa', 'asc')
                ->orderBy('codigo_contrato', 'asc')
                ->get()
                ->groupBy('id_empresa');

            // Calcular estado de pago dinámicamente para cada contrato
            $estadosPagoContratos = [];
            
            foreach ($todosContratos as $idEmpresa => $contratosEmpresa) {
                $totalPagado = $pagosPorEmpresa->get($idEmpresa, 0);
                $pagoAcumulado = 0;

                foreach ($contratosEmpresa as $contrato) {
                    $totalContrato = $contrato->descuento > 0 
                        ? $contrato->precio_total_desc 
                        : $contrato->precio_total;

                    $pagoDisponible = $totalPagado - $pagoAcumulado;
                    
                    if ($pagoDisponible <= 0) {
                        $estadosPagoContratos[$contrato->id_contrato] = 0; // Sin pago
                    } else {
                        $pagoAplicado = min($pagoDisponible, $totalContrato);
                        $pagoAcumulado += $pagoAplicado;

                        if ($pagoAplicado >= $totalContrato) {
                            $estadosPagoContratos[$contrato->id_contrato] = 2; // Pago completo
                        } else {
                            $estadosPagoContratos[$contrato->id_contrato] = 1; // Pago parcial
                        }
                    }
                }
            }

            // Procesar cada pabellón y generar imagen con marcado
            $reportePabellones = [];
            $cacheTokenMapas = now()->format('Uu');
            $totales = [
                'total_stands' => 0,
                'pagado_100' => 0,
                'pago_parcial' => 0,
                'con_contrato' => 0,
                'reservado' => 0,
                'libres' => 0,
            ];

            foreach ($pabellones as $pabellon) {
                $stands = $standsMap->get($pabellon->id_pabellon, collect());
                $totalStands = $stands->count();

                $estadisticas = [
                    'total_stands' => $totalStands,
                    'pagado_100' => 0,
                    'pago_parcial' => 0,
                    'con_contrato' => 0,
                    'reservado' => 0,
                    'libres' => 0,
                ];

                // Agrupar ocupaciones por contrato para este pabellón
                $contratosPorPabellon = [];
                $standsOcupados = collect();

                foreach ($stands as $stand) {
                    $ocupacion = $ocupaciones->get($stand->id_stand)?->first();

                    if ($ocupacion && $ocupacion->contrato) {
                        $contrato = $ocupacion->contrato;
                        $idContrato = $contrato->id_contrato;

                        $estado = $this->resolverEstadoStand($contrato, $estadosPagoContratos);

                        if ($estado === 'pago_parcial') {
                            $estadisticas['pago_parcial']++;
                        } elseif ($estado === 'pagado_100') {
                            $estadisticas['pagado_100']++;
                        } elseif ($estado === 'con_contrato') {
                            $estadisticas['con_contrato']++;
                        } elseif ($estado === 'reservado') {
                            $estadisticas['reservado']++;
                        }

                        // Marcar stand como ocupado
                        $standsOcupados->push($stand->id_stand);

                        // Agrupar stands por contrato
                        if (!isset($contratosPorPabellon[$idContrato])) {
                            $contratosPorPabellon[$idContrato] = [
                                'id_contrato' => $idContrato,
                                'stands' => [],
                                'stands_texto' => '',
                                'estado' => $estado,
                                'empresa' => $contrato->empresa ? [
                                    'id_empresa' => $contrato->empresa->id_empresa,
                                    'nombre_empresa' => $contrato->empresa->nombre_empresa,
                                ] : null,
                            ];
                        }

                        // Agregar stand al contrato
                        $contratosPorPabellon[$idContrato]['stands'][] = $stand->numero_stand;
                    } else {
                        $estadisticas['libres']++;
                    }
                }

                // Convertir array de contratos a formato final
                $standsDetalle = [];
                foreach ($contratosPorPabellon as $contratoData) {
                    // Crear texto de stands (ej: "1, 2, 3")
                    $contratoData['stands_texto'] = implode(', ', $contratoData['stands']);
                    unset($contratoData['stands']); // Remover array temporal
                    $standsDetalle[] = $contratoData;
                }

                // Generar imagen del pabellón con marcado de stands ocupados
                $this->generarImagenPabellon($idFeria, $pabellon->id_pabellon, $stands, $ocupaciones, $estadosPagoContratos);

                // Calcular porcentajes
                $porcentajes = [
                    'pagado_100' => $totalStands > 0 ? round(($estadisticas['pagado_100'] / $totalStands) * 100, 2) : 0,
                    'pago_parcial' => $totalStands > 0 ? round(($estadisticas['pago_parcial'] / $totalStands) * 100, 2) : 0,
                    'con_contrato' => $totalStands > 0 ? round(($estadisticas['con_contrato'] / $totalStands) * 100, 2) : 0,
                    'reservado' => $totalStands > 0 ? round(($estadisticas['reservado'] / $totalStands) * 100, 2) : 0,
                    'libres' => $totalStands > 0 ? round(($estadisticas['libres'] / $totalStands) * 100, 2) : 0,
                ];

                $reportePabellones[] = [
                    'id_pabellon' => $pabellon->id_pabellon,
                    'nombre_pabellon' => $pabellon->nombre_pabellon,
                    'mapa_url' => "/img/pabellones_estado/{$idFeria}_{$pabellon->id_pabellon}.png?v={$cacheTokenMapas}",
                    'estadisticas' => $estadisticas,
                    'porcentajes' => $porcentajes,
                    'stands' => $standsDetalle,
                ];

                // Acumular totales generales
                $totales['total_stands'] += $estadisticas['total_stands'];
                $totales['pagado_100'] += $estadisticas['pagado_100'];
                $totales['pago_parcial'] += $estadisticas['pago_parcial'];
                $totales['con_contrato'] += $estadisticas['con_contrato'];
                $totales['reservado'] += $estadisticas['reservado'];
                $totales['libres'] += $estadisticas['libres'];
            }

            // Calcular porcentajes totales
            $porcentajesTotales = [
                'pagado_100' => $totales['total_stands'] > 0 ? round(($totales['pagado_100'] / $totales['total_stands']) * 100, 2) : 0,
                'pago_parcial' => $totales['total_stands'] > 0 ? round(($totales['pago_parcial'] / $totales['total_stands']) * 100, 2) : 0,
                'con_contrato' => $totales['total_stands'] > 0 ? round(($totales['con_contrato'] / $totales['total_stands']) * 100, 2) : 0,
                'reservado' => $totales['total_stands'] > 0 ? round(($totales['reservado'] / $totales['total_stands']) * 100, 2) : 0,
                'libres' => $totales['total_stands'] > 0 ? round(($totales['libres'] / $totales['total_stands']) * 100, 2) : 0,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'feria' => [
                        'id' => $feria->id_feria,
                        'nombre' => $feria->nombre_feria,
                        'codigo' => $feria->codigo_contrato,
                        'estado' => $feria->estado_feria,
                    ],
                    'pabellones' => $reportePabellones,
                    'totales' => $totales,
                    'porcentajes_totales' => $porcentajesTotales,
                ],
                        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                            ->header('Pragma', 'no-cache');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el estado de ventas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar imagen del pabellón con marcado de stands ocupados
     * Usa GD para pintar sobre la imagen base del pabellón
     * 
     * @param int $idFeria ID de la feria
     * @param int $idPabellon ID del pabellón
     * @param \Illuminate\Support\Collection $stands Colección de stands
     * @param \Illuminate\Support\Collection $ocupaciones Mapa de ocupaciones agrupadas por id_stand
     * @param array $estadosPagoContratos Estados de pago calculados dinámicamente
     * @return void
     */
    private function generarImagenPabellon($idFeria, $idPabellon, $stands, $ocupaciones, $estadosPagoContratos)
    {
        try {
            // Ruta de la imagen base del pabellón
            $rutaImagenBase = public_path("img/pabellones/{$idFeria}_{$idPabellon}.png");
            
            // Si no existe la imagen base, no hacer nada
            if (!file_exists($rutaImagenBase)) {
                return;
            }

            // Cargar imagen base
            $imagen = imagecreatefrompng($rutaImagenBase);
            
            if (!$imagen) {
                return;
            }

            // Procesar cada stand y pintar según su estado
            foreach ($stands as $stand) {
                $ocupacion = $ocupaciones->get($stand->id_stand)?->first();
                
                if (!$ocupacion || !$ocupacion->contrato) {
                    continue; // Stand libre, no pintar
                }

                $contrato = $ocupacion->contrato;

                $estado = $this->resolverEstadoStand($contrato, $estadosPagoContratos);
                $color = $this->obtenerColorEstadoPlano($imagen, $estado);

                if ($color === null) {
                    continue;
                }

                // Pintar el stand según sus coordenadas
                if (!empty($stand->coord)) {
                    $coords = array_values(array_filter(array_map('trim', explode(',', (string)$stand->coord)), static fn ($v) => $v !== ''));
                    $tipo = (int)($stand->tipo ?? 0);
                    $borde = imagecolorallocatealpha($imagen, 0, 0, 0, 30);

                    if ($tipo == 1 && count($coords) >= 4) {
                        // Rectángulo
                        $x1 = (int)$coords[0];
                        $y1 = (int)$coords[1];
                        $x2 = (int)$coords[2];
                        $y2 = (int)$coords[3];

                        imagefilledrectangle(
                            $imagen,
                            $x1,
                            $y1,
                            $x2,
                            $y2,
                            $color
                        );
                        imagerectangle($imagen, $x1, $y1, $x2, $y2, $borde);
                    } elseif ($tipo == 2 && count($coords) >= 6 && (count($coords) % 2 === 0)) {
                        // Polígono
                        $coordsInt = array_map('intval', $coords);
                        imagefilledpolygon($imagen, $coordsInt, $color);
                        imagepolygon($imagen, $coordsInt, $borde);
                    }
                }
            }

            // Crear directorio si no existe
            $directorioDestino = public_path('img/pabellones_estado');
            if (!file_exists($directorioDestino)) {
                mkdir($directorioDestino, 0755, true);
            }

            // Guardar la imagen generada
            $rutaImagenDestino = "{$directorioDestino}/{$idFeria}_{$idPabellon}.png";
            imagepng($imagen, $rutaImagenDestino);
            
            // Liberar memoria
            imagedestroy($imagen);

        } catch (\Exception $e) {
            // En caso de error, simplemente no generar la imagen
            // El sistema seguirá funcionando sin ella
            Log::error("Error generando imagen del pabellón {$idPabellon}: " . $e->getMessage());
        }
    }

    private function resolverEstadoStand($contrato, array $estadosPagoContratos): string
    {
        $pago = $estadosPagoContratos[$contrato->id_contrato] ?? 0;
        $estadoReserva = (int)($contrato->estado_reserva ?? 0);

        if ($pago === 1) {
            return 'pago_parcial';
        }

        if ($pago >= 2) {
            return 'pagado_100';
        }

        if ($estadoReserva === 3) {
            return 'con_contrato';
        }

        if ($estadoReserva === 2) {
            return 'reservado';
        }

        return 'libre';
    }

    private function obtenerColorEstadoPlano($imagen, string $estado): ?int
    {
        return match ($estado) {
            'pagado_100' => imagecolorallocatealpha($imagen, 62, 95, 138, 60),
            'pago_parcial' => imagecolorallocatealpha($imagen, 96, 255, 96, 60),
            'con_contrato' => imagecolorallocatealpha($imagen, 255, 255, 0, 60),
            'reservado' => imagecolorallocatealpha($imagen, 0, 207, 232, 60),
            default => null,
        };
    }
}
