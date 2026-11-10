<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredencialPinEnvio extends Model
{
    protected $table = 'credencial_pin_envios';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id_pin',
        'id_feria',
        'id_empresa',
        'lote_uuid',
        'tipo_credencial',
        'correo_destinatario',
        'tipo_envio',
        'canal',
        'cantidad_pines',
        'id_usuario',
        'created_at',
    ];

    protected $casts = [
        'id_pin' => 'integer',
        'id_feria' => 'integer',
        'id_empresa' => 'integer',
        'cantidad_pines' => 'integer',
        'id_usuario' => 'integer',
        'created_at' => 'datetime',
    ];
}
