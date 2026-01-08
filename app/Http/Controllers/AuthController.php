<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'nick_usuario' => ['required', 'string'],
            'pass_usuario' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        $user = Usuario::where('nick_usuario', $credentials['nick_usuario'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'Las credenciales no coinciden con nuestros registros.',
            ], 422);
        }

        $plainPassword = $credentials['pass_usuario'];
        $storedPassword = $user->pass_usuario;
        $isHashed = Str::startsWith($storedPassword, '$2y$');
        $passwordMatches = $isHashed
            ? Hash::check($plainPassword, $storedPassword)
            : hash_equals($storedPassword, $plainPassword);

        if (! $isHashed && $passwordMatches) {
            $user->pass_usuario = $plainPassword;
            $user->save();
        }

        if (! $passwordMatches) {
            return response()->json([
                'message' => 'Las credenciales no coinciden con nuestros registros.',
            ], 422);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json([
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

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
