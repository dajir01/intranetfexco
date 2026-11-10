<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credencial extends Model
{
    protected $table = 'credenciales';

    protected $primaryKey = 'id_credencial';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_feria',
        'tipo_credencial',
        'modalidad_credencial',
        'estado_credencial',
        'id_expositor',
        'com_medio',
        'com_dias',
        'ingreso',
        'cod_credencial',
    ];
}
