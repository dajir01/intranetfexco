<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Anulacion_Ingreso extends Model
{
    protected $table = 'i_anulacion_ingreso';
    protected $primaryKey = 'id_anulacion_ingreso';
    public $timestamps = false;

    protected $fillable = [
        'ingreso_id',
        'usuario',
        'motivo',
        'fecha_anulacion',
    ];
}
