<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subrubro extends Model
{
    protected $table = 'subrubros';
    protected $primaryKey = 'id_subrubro';
    public $timestamps = false;
    protected $fillable = [
        'nombre_subrubro',
        'id_rubro',
    ];

    public function rubro()
    {
        return $this->belongsTo(Rubro::class, 'id_rubro', 'id_rubro');
    }
}
