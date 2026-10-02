<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Detalle_Movimiento extends Model
{
    protected $table = 'i_detalle_movimientos';
    protected $primaryKey = 'id_detalle_movimiento';
    public $timestamps = false;

    protected $fillable = [
        'movimiento_id',
        'asignacion_id',
        'cantidad',
        'costo',
        'total',
    ];
}
