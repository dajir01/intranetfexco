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
    public $incrementing = false;
    protected $keyType = 'string';
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
        if (empty($value)) {
            $this->attributes['pass_usuario'] = $value;

            return;
        }

        $this->attributes['pass_usuario'] = Str::startsWith($value, '$2y$')
            ? $value
            : Hash::make($value);
    }
}
