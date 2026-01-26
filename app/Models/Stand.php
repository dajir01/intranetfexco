<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stand extends Model
{
    protected $table = 'stands';
    protected $primaryKey = 'id_stand';
    public $timestamps = false;

    protected $fillable = [
        'id_pabellon',
        'numero_stand',
        'area_stand',
        'precio_stand',
        'sup',
        'izq',
        'coord',
        'lat',
        'lon',
        'tipo',
        'anterior',
        'feria'
    ];
}
