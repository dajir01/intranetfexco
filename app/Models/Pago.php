<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pago';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'id_feria',
        'id_empresa',
        'fecha',
        'monto',
        'estado',
        'tipo_pago',
        'extension',
        'obs',
        'fecha_aprobacion',
        'id_usuario',
        'id_aprobacion',
    ];

    protected $guarded = [];

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Relación con Usuario (quien registró el pago)
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Relación con Usuario de Aprobación
     */
    public function usuarioAprobacion()
    {
        return $this->belongsTo(Usuario::class, 'id_aprobacion', 'id_usuario');
    }

    /**
     * Relación con Feria
     */
    public function feria()
    {
        return $this->belongsTo(Feria::class, 'id_feria', 'id_feria');
    }

    /**
     * Relación con Recibo generado para este pago
     */
    public function recibo()
    {
        return $this->hasOne(Recibo::class, 'id_pago', 'id');
    }
}
