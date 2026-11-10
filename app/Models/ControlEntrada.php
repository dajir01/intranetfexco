<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControlEntrada extends Model
{
    protected $table = 'control_entradas';

    protected $primaryKey = 'id_control_entrada';

    public $timestamps = false;

    protected $fillable = [
        'id_feria',
        'nombre_entrada',
        'precio',
        'tipo',
        'rango_inicial',
        'rango_final',
        'estado',
    ];
}
