<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredencialPin extends Model
{
    protected $table = 'credencial_pines';

    protected $fillable = [
        'id_feria',
        'id_empresa',
        'id_contrato',
        'id_empresa_evento_credencial',
        'tipo_credencial',
        'modalidad_credencial',
        'pin_hash',
        'pin_encrypted',
        'estado',
        'id_credencial',
        'nombre_destinatario',
        'celular_destinatario',
        'email_destinatario',
        'intentos_fallidos',
        'generado_por',
        'usado_at',
        'revocado_at',
        'ultimo_envio_at',
        'ultimo_canal_envio',
    ];

    protected $casts = [
        'modalidad_credencial' => 'integer',
        'id_feria' => 'integer',
        'id_empresa' => 'integer',
        'id_contrato' => 'integer',
        'id_empresa_evento_credencial' => 'integer',
        'id_credencial' => 'integer',
        'intentos_fallidos' => 'integer',
        'usado_at' => 'datetime',
        'revocado_at' => 'datetime',
        'ultimo_envio_at' => 'datetime',
    ];

    public function feria(): BelongsTo
    {
        return $this->belongsTo(Feria::class, 'id_feria', 'id_feria');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }
}
