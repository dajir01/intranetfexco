<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pabellon extends Model
{
    protected $table = 'pabellones';
    protected $primaryKey = 'id_pabellon';
    public $timestamps = false;

    protected $fillable = [
        'nombre_pabellon',
        'feria',
    ];

    protected $appends = ['mapa_url'];

    /**
     * Obtener la URL del mapa construida dinámicamente
     * siguiendo la convención del sistema antiguo
     */
    public function getMapaUrlAttribute()
    {
        return "/img/pabellones/{$this->feria}_{$this->id_pabellon}.png";
    }

    /**
     * Relación con Feria
     */
    public function feria()
    {
        return $this->belongsTo(Feria::class, 'feria', 'id_feria');
    }
}
