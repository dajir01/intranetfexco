<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feria extends Model
{
    protected $table = 'eventos';
    protected $primaryKey = 'id_feria';
    public $timestamps = false;

    protected $fillable = [
        'nombre_feria',
        'puertas_acceso',
        'info',
        'codigo_contrato',
        'codigo_factura',
        'inicio',
        'fecha_inicio',
        'fecha_fin',
        'estado_feria',
        'cred_inicio',
        'tipo_credenciales',
        'id_modelocontrato',
        'id_modeloademda'
    ];

    protected $guarded = [];

    public function scopeOrderByFechaEvento($query, string $direction = 'desc')
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $sqlDirection = strtoupper($direction);

        return $query
            ->orderByRaw("COALESCE(fecha_inicio, inicio) {$sqlDirection}")
            ->orderBy('id_feria', $direction);
    }

    /**
     * Relación con Pabellones
     */
    public function pabellones()
    {
        return $this->hasMany(Pabellon::class, 'feria', 'id_feria')
            ->orderBy('nombre_pabellon', 'asc');
    }

    public function reglamentos()
    {
        return $this->hasMany(ReglamentoFeria::class, 'id_feria', 'id_feria');
    }
}
