<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Login endpoint con validación de estado del usuario.
     * 
     * Flujo de seguridad:
     * 1. Validar credenciales de entrada
     * 2. Buscar usuario por nick_usuario
     * 3. Verificar estado del usuario (debe ser 1 para estar activo)
     * 4. Validar contraseña (hasheada o en texto plano)
     * 5. Iniciar sesión si todo es válido
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'nick_usuario' => ['required', 'string', 'max:255'],
            'pass_usuario' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        // 1. Buscar usuario por nick
        $user = Usuario::where('nick_usuario', $credentials['nick_usuario'])->first();

        if (!$user) {
            Log::warning('Intento de login con usuario inexistente', [
                'nick_usuario' => $credentials['nick_usuario'],
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'message' => 'Las credenciales no coinciden con nuestros registros.',
            ], 422);
        }

        // 2. Verificar estado ANTES de validar contraseña (seguridad)
        if (!$this->isUserActive($user)) {
            Log::warning('Intento de login con usuario inactivo', [
                'nick_usuario' => $user->nick_usuario,
                'estado' => $user->estado ?? 'null',
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'message' => 'Las credenciales no coinciden con nuestros registros.',
            ], 422);
        }

        // 3. Validar contraseña
        $plainPassword = $credentials['pass_usuario'];
        $storedPassword = $user->pass_usuario;
        
        // Verificar si la contraseña está correctamente hasheada
        $isHashed = Str::startsWith($storedPassword, '$2y$');
        
        // Comparar contraseña según su estado
        $passwordMatches = $this->verifyPassword(
            $plainPassword,
            $storedPassword,
            $isHashed
        );

        if (!$passwordMatches) {
            Log::warning('Intento de login con contraseña incorrecta', [
                'nick_usuario' => $user->nick_usuario,
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'message' => 'Las credenciales no coinciden con nuestros registros.',
            ], 422);
        }

        // 4. Iniciar sesión
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        Log::info('Login exitoso', [
            'nick_usuario' => $user->nick_usuario,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Verifica si un usuario está activo.
     * Un usuario está activo si:
     * - Tiene estado = 1, O
     * - El campo estado no existe/está null (compatibilidad hacia atrás)
     */
    protected function isUserActive(Usuario $user): bool
    {
        // Si el campo estado no existe o es null, permitir acceso (compatibilidad)
        if ($user->estado === null) {
            return true;
        }

        // El usuario debe tener estado = 1 para estar activo
        return (int)$user->estado === 1;
    }

    /**
     * Verifica si una contraseña coincide con el valor almacenado.
     * Maneja tanto contraseñas hasheadas como en texto plano.
     */
    protected function verifyPassword(string $plain, string $stored, bool $isHashed): bool
    {
        if ($isHashed) {
            // Comparar con contraseña hasheada usando bcrypt
            return Hash::check($plain, $stored);
        }

        // Comparar texto plano de forma segura (timing-safe comparison)
        // Nota: Esto solo se usa para migración de contraseñas antiguas
        return hash_equals($stored, $plain);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            Log::info('Logout exitoso', [
                'nick_usuario' => $user->nick_usuario,
            ]);
        }

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
}
