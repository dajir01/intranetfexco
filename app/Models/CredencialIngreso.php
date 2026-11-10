<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredencialIngreso extends Model
{
    protected $table = 'credencial_ingresos';

    public $timestamps = false;

    protected $fillable = [
        'id_credencial',
        'id_feria',
        'id_usuario_control',
        'numero_puerta',
        'id_qr_token',
        'tipo_credencial',
        'modalidad_credencial',
        'fecha_ingreso',
        'idempotency_key',
        'dispositivo_ref',
        'punto_acceso',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'id_credencial' => 'integer',
            'id_feria' => 'integer',
            'id_usuario_control' => 'integer',
            'numero_puerta' => 'integer',
            'id_qr_token' => 'integer',
            'tipo_credencial' => 'integer',
            'modalidad_credencial' => 'integer',
            'fecha_ingreso' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
