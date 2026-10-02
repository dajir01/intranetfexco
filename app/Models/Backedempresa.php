<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backedempresa extends Model
{
    protected $table = 'backedempresa';
    protected $primaryKey = 'id_backedempresa';
    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'modificado_por',
        'datos_antes',
        'datos_despues',
        'fecha',
    ];

    protected $casts = [
        'datos_antes' => 'array',
        'datos_despues' => 'array',
        'fecha' => 'datetime',
    ];
}
