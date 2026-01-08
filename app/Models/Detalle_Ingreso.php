<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Detalle_Ingreso extends Model
{
    protected $table = 'i_detalle_ingreso';
    protected $primaryKey = 'id_detalle_ingreso';
    public $timestamps = false;

    protected $fillable = [
        'ingreso_id',
        'asignacion_id',
        'cantidad',
        'costo',
        'precio',
        'importe',
    ];
}
