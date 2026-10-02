<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modelo_Contrato extends Model
{
    protected $table = 'modelo_contrato';
    protected $primaryKey = 'id_modelo_contrato';
    public $timestamps = false;
    protected $fillable = [
        'nombre',
        'fecha',
        'estado',
        'ruta_documento',
        'tipo',
    ];
}
