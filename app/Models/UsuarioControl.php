<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

class UsuarioControl extends Authenticatable
{
    protected $table = 'usuarios_control';

    protected $primaryKey = 'id_usuario_control';

    protected $fillable = [
        'nombre',
        'usuario',
        'password',
        'activo',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function setPasswordAttribute(string $password): void
    {
        $this->attributes['password'] = Hash::make($password);
    }
}
