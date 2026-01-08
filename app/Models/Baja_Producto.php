<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Baja_Producto extends Model
{
    protected $table = 'i_bajas_productos';
    protected $primaryKey = 'id_baja';
    public $timestamps = false;

    protected $fillable = [
        'asignacion_id',
        'fecha_baja',
        'motivo',
        'usuario_registra',
    ];
}
