<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    protected $table = 'paises';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'nombre',
    ];

    public function localidades()
    {
        return $this->hasMany(Localidad::class, 'id_pais', 'id');
    }
}
