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
    ];

    protected $guarded = [];

    /**
     * Relación con Pabellones
     */
    public function pabellones()
    {
        return $this->hasMany(Pabellon::class, 'feria', 'id_feria')
            ->orderBy('nombre_pabellon', 'asc');
    }
}
