<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LimiteCredencial extends Model
{
    protected $table = 'limite_credenciales';

    public $timestamps = false;

    protected $fillable = [
        'id_feria',
        'tipo_area',
        'limite_sup',
        'cant_credenciales',
        'pot_contratada',
        'lim_entradas',
    ];

    protected $casts = [
        'limite_sup' => 'float',
        'cant_credenciales' => 'integer',
        'pot_contratada' => 'integer',
        'lim_entradas' => 'integer',
    ];
}
