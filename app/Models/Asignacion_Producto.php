<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asignacion_Producto extends Model
{
    protected $table = 'i_asignaciones_productos';
    protected $primaryKey = 'id_asignacion';
    public $timestamps = false;

    protected $fillable = [
        'producto_id',
        'area_id',
        'codigo',
        'stock',
        'costo_total',
        'fecha_asignacion',
        'estado_dado_baja',
        'estado_movimiento',
    ];

    /**
     * Relación con Producto
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'id_producto');
    }

    /**
     * Relación con Area
     */
    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id', 'id_area');
    }
}

