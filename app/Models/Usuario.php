<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'nombre_usuario',
        'nick_usuario',
        'pass_usuario',
        'nivel_usuario',
        'area',
        'jefatura',
        'email',
        'creado_por',
        'estado',
    ];

    protected $hidden = [
        'pass_usuario',
        'remember_token',
    ];

    public function getAuthIdentifierName()
    {
        return 'nick_usuario';
    }

    public function getAuthPassword()
    {
        return $this->pass_usuario;
    }

    public function setPassUsuarioAttribute($value)
    {
        // Guardar la contraseña tal como se recibe (texto plano o hash ya generado)
        $this->attributes['pass_usuario'] = $value ?? '';
    }

    /**
     * Verifica si el usuario está activo.
     * Un usuario está activo si:
     * - Tiene estado = 1, O
     * - El campo estado es null (compatibilidad hacia atrás)
     */
    public function isActive(): bool
    {
        // Si no hay estado definido, considerar activo (compatibilidad)
        if ($this->estado === null) {
            return true;
        }

        return (int)$this->estado === 1;
    }

    /**
     * Verifica si el usuario está inactivo.
     */
    public function isInactive(): bool
    {
        return !$this->isActive();
    }
}

