<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Anulacion_Detalle extends Model
{
    protected $table = 'i_anulacion_detalle';
    protected $primaryKey = 'id_anulacion_detalle';
    public $timestamps = false;

    protected $fillable = [
        'anulacion_ingreso_id',
        'asignacion_id',
        'cantidad_revertida',
        'stock_previo',
        'stock_resultante',
    ];
}
