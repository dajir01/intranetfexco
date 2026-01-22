<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsuarioController extends Controller
{
    /**
     * Lista de usuarios con búsqueda, orden y paginación.
     * Parámetros opcionales:
     * - q: término de búsqueda (nombre_usuario, email)
     * - page: página (por defecto 1)
     * - per_page: elementos por página (1..100, por defecto 10)
     * - sort_by: columna de orden (nombre_usuario, area, estado, email)
     * - sort_dir: asc|desc (por defecto asc)
     */
    public function usuarios(Request $request): JsonResponse
    {
        $allowedSort = ['nombre_usuario', 'area', 'estado', 'email', 'id_usuario'];

        $search = (string) $request->input('q', '');
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $page = (int) $request->input('page', 1);
        $sortBy = $request->input('sort_by', 'nombre_usuario');
        if (!in_array($sortBy, $allowedSort, true))
            $sortBy = 'nombre_usuario';
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Usuario::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('nombre_usuario', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('nick_usuario', 'like', $like);
            });
        }

        $select = [
            'id_usuario',
            'nombre_usuario',
            'email',
            'area',
            'estado',
        ];

        $paginator = $query
            ->select($select)
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage, ['*'], 'page', $page);

        Log::info('Listado de usuarios obtenido', [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'search' => $search,
        ]);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'q' => $search,
            ],
        ]);
    }

    /**
     * Obtiene los detalles de un usuario por ID.
     * Parámetros:
     * - id: ID del usuario (id_usuario)
     */
    public function show(string $id): JsonResponse
    {
        try {
            $usuario = Usuario::find($id);

            if (!$usuario) {
                Log::warning('Usuario no encontrado', [
                    'id_usuario' => $id,
                    'ip' => request()->ip(),
                ]);

                return response()->json([
                    'message' => 'Usuario no encontrado',
                    'error' => 'not_found',
                ], 404);
            }

            Log::info('Detalle de usuario obtenido', [
                'id_usuario' => $id,
            ]);

            // Incluir pass_usuario que está oculto por defecto
            $usuarioData = $usuario->toArray();
            $usuarioData['pass_usuario'] = $usuario->pass_usuario;

            return response()->json([
                'data' => $usuarioData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalle de usuario', [
                'id_usuario' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al obtener usuario',
                'error' => 'server_error',
            ], 500);
        }
    }

    /**
     * Crea un nuevo usuario.
     * Valida datos requeridos y hashea la contraseña antes de guardar.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nombre_usuario' => 'required|string|max:255',
                'nick_usuario' => 'required|string|max:255|unique:usuarios,nick_usuario',
                'email' => 'required|email|max:255|unique:usuarios,email',
                'area' => 'required|string|max:255',
                'nivel_usuario' => 'required|integer|min:1|max:8',
                'jefatura' => 'required|integer|in:0,1',
                'pass_usuario' => 'required|string|min:4|max:255',
            ], [
                'nombre_usuario.required' => 'El nombre es obligatorio',
                'nick_usuario.required' => 'El usuario es obligatorio',
                'nick_usuario.unique' => 'El usuario ya existe',
                'email.required' => 'El email es obligatorio',
                'email.email' => 'Email inválido',
                'email.unique' => 'El email ya está registrado',
                'area.required' => 'El área es obligatoria',
                'nivel_usuario.required' => 'El nivel de usuario es obligatorio',
                'nivel_usuario.integer' => 'El nivel debe ser numérico',
                'nivel_usuario.min' => 'El nivel debe estar entre 1 y 8',
                'nivel_usuario.max' => 'El nivel debe estar entre 1 y 8',
                'jefatura.required' => 'El rol de jefatura es obligatorio',
                'jefatura.in' => 'El rol de jefatura debe ser 0 o 1',
                'pass_usuario.required' => 'La contraseña es obligatoria',
                'pass_usuario.min' => 'La contraseña debe tener al menos 4 caracteres',
                'pass_usuario.max' => 'La contraseña no puede exceder 255 caracteres',
            ]);

            $usuario = new Usuario();
            $usuario->nombre_usuario = $validated['nombre_usuario'];
            $usuario->nick_usuario = $validated['nick_usuario'];
            $usuario->email = $validated['email'];
            $usuario->area = $validated['area'];
            $usuario->nivel_usuario = $validated['nivel_usuario'];
            $usuario->jefatura = $validated['jefatura'];
            $usuario->estado = 1; // Activo por defecto
            $usuario->creado_por = auth()->user()->id_usuario ?? 0;
            $usuario->remember_token = Str::random(40);
            $usuario->pass_usuario = $validated['pass_usuario'];

            $usuario->save();

            Log::info('Usuario creado correctamente', [
                'id_usuario' => $usuario->id_usuario,
                'nick_usuario' => $usuario->nick_usuario,
            ]);

            return response()->json([
                'message' => 'Usuario creado correctamente',
                'data' => $usuario->toArray(),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validación fallida al crear usuario', [
                'errores' => $e->errors(),
            ]);

            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al crear usuario', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => 'server_error',
            ], 500);
        }
    }

    /**
     * Actualiza los datos de un usuario.
     * Campos permitidos: nombre_usuario, email, area (texto), nivel_usuario (número), jefatura, pass_usuario (opcional)
     * NO permite modificar: nick_usuario, estado
     * 
     * Parámetros:
     * - id: ID del usuario (id_usuario)
     * - nombre_usuario: Nombre completo (requerido)
     * - email: Email (requerido, único)
     * - area: Área del usuario como texto (requerido, ej: "Sistemas")
     * - nivel_usuario: Nivel del usuario como número (requerido, 1-8)
     * - jefatura: Rol de jefatura (requerido, 0 o 1)
     * - pass_usuario: Contraseña (opcional, solo si se desea cambiar)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            // Buscar el usuario
            $usuario = Usuario::find($id);

            if (!$usuario) {
                Log::warning('Usuario no encontrado para actualización', [
                    'id_usuario' => $id,
                    'ip' => request()->ip(),
                ]);

                return response()->json([
                    'message' => 'Usuario no encontrado',
                    'error' => 'not_found',
                ], 404);
            }

            // Validar los datos de entrada
            $validated = $request->validate([
                'nombre_usuario' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:usuarios,email,' . $id . ',id_usuario',
                'area' => 'required|string|max:255',
                'nivel_usuario' => 'required|integer|min:1|max:8',
                'jefatura' => 'required|integer|in:0,1',
                'pass_usuario' => 'nullable|string|min:4|max:255',
            ], [
                'nombre_usuario.required' => 'El nombre es obligatorio',
                'nombre_usuario.max' => 'El nombre no puede exceder 255 caracteres',
                'email.required' => 'El email es obligatorio',
                'email.email' => 'El email no es válido',
                'email.unique' => 'El email ya está registrado',
                'area.required' => 'El área es obligatoria',
                'area.string' => 'El área debe ser texto',
                'nivel_usuario.required' => 'El nivel de usuario es obligatorio',
                'nivel_usuario.integer' => 'El nivel de usuario debe ser un número válido',
                'nivel_usuario.min' => 'El nivel de usuario debe estar entre 1 y 8',
                'nivel_usuario.max' => 'El nivel de usuario debe estar entre 1 y 8',
                'jefatura.required' => 'El rol de jefatura es obligatorio',
                'jefatura.in' => 'El rol de jefatura debe ser 0 o 1',
                'pass_usuario.min' => 'La contraseña debe tener al menos 4 caracteres',
                'pass_usuario.max' => 'La contraseña no puede exceder 255 caracteres',
            ]);

            // Actualizar solo los campos permitidos
            $usuario->nombre_usuario = $validated['nombre_usuario'];
            $usuario->email = $validated['email'];
            $usuario->area = $validated['area'];
            $usuario->nivel_usuario = $validated['nivel_usuario'];
            $usuario->jefatura = $validated['jefatura'];

            // Actualizar contraseña solo si se proporcionó
            if (!empty($validated['pass_usuario'])) {
                $usuario->pass_usuario = $validated['pass_usuario'];
            }

            // Guardar cambios
            $usuario->save();

            Log::info('Usuario actualizado correctamente', [
                'id_usuario' => $id,
                'campos_actualizados' => array_keys($validated),
                'contrasena_actualizada' => !empty($validated['pass_usuario']),
                'ip' => request()->ip(),
            ]);

            return response()->json([
                'message' => 'Usuario actualizado correctamente',
                'data' => $usuario->toArray(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validación fallida al actualizar usuario', [
                'id_usuario' => $id,
                'errores' => $e->errors(),
            ]);

            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al actualizar usuario', [
                'id_usuario' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => 'server_error',
            ], 500);
        }
    }

    /**
     * Cambia el estado (activo/inactivo) de un usuario
     * Parámetros:
     * - estado: 0|1
     */
    public function toggleEstado(Request $request, string $id): JsonResponse
    {
        try {
            $usuario = Usuario::find($id);

            if (!$usuario) {
                Log::warning('Usuario no encontrado para cambio de estado', [
                    'id_usuario' => $id,
                    'ip' => request()->ip(),
                ]);

                return response()->json([
                    'message' => 'Usuario no encontrado',
                    'error' => 'not_found',
                ], 404);
            }

            $validated = $request->validate([
                'estado' => 'required|integer|in:0,1',
            ], [
                'estado.required' => 'El estado es obligatorio',
                'estado.in' => 'El estado debe ser 0 o 1',
            ]);

            $usuario->estado = $validated['estado'];
            $usuario->save();

            Log::info('Estado de usuario actualizado', [
                'id_usuario' => $id,
                'estado' => $validated['estado'],
                'ip' => request()->ip(),
            ]);

            return response()->json([
                'message' => 'Estado actualizado correctamente',
                'data' => $usuario->toArray(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validación fallida al cambiar estado de usuario', [
                'id_usuario' => $id,
                'errores' => $e->errors(),
            ]);

            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de usuario', [
                'id_usuario' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al cambiar estado de usuario',
                'error' => 'server_error',
            ], 500);
        }
    }
}
