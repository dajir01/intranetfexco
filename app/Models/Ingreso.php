<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingreso extends Model
{
    protected $table = 'i_ingresos';
    protected $primaryKey = 'id_ingreso';
    public $timestamps = false;

    protected $fillable = [
        'numero',
        'proveedor_id',
        'factura_numero',
        'fecha_factura',
        'fecha_ingreso',
        'persona_recibe',
        'persona_entrega',
        'importe',
        'Observaciones',
        'estado',
    ];
}
