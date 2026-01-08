<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $table = 'i_movimiento';
    protected $primaryKey = 'id_movimiento';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'area',
        'fecha',
        'persona_entrega',
        'persona_recibe',
        'observaciones',
        'tipo',
        'total',
    ];
}
