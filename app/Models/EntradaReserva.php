<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntradaReserva extends Model
{
    protected $table = 'entrada_reservas';

    protected $fillable = [
        'id_control_entrada',
        'user_id',
        'rango_inicio',
        'rango_fin',
        'estado',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
