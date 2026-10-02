<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rubro extends Model
{
    protected $table = 'rubros';
    protected $primaryKey = 'id_rubro';
    public $timestamps = false;
    protected $fillable = [
        'nombre_rubro',
    ];

    public function subrubros()
    {
        return $this->hasMany(Subrubro::class, 'id_rubro', 'id_rubro');
    }
}
