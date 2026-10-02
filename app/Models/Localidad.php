<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Localidad extends Model
{
    protected $table = 'localidades';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'nombre',
        'id_pais',
    ];

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'id_pais', 'id');
    }
}
