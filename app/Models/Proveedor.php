<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'i_proveedores';
    protected $primaryKey = 'id_proveedores';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];
}
