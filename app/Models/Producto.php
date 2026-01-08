<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'i_producto';
    protected $primaryKey = 'id_producto';
    public $timestamps = false;

    protected $fillable = [
        'area_id',
        'codigo_barras',
        'nombre',
        'descripcion',
        'tipo',
        'unidad_medida',
        'permite_ingreso',
        'estado_dado_baja',
        'estado_movimiento',
    ];
}
