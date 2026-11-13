<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaEventoCredencial extends Model
{
    protected $table = 'empresa_evento_credenciales';

    protected $fillable = [
        'id_empresa',
        'id_feria',
        'tipo_credencial',
        'tipo_credenciales',
        'cantidad_credenciales',
        'id_contrato',
        'estado',
        'visible_en_plataforma',
        'id_usuario_creacion',
    ];

    protected $casts = [
        'cantidad_credenciales' => 'integer',
        'estado' => 'boolean',
        'visible_en_plataforma' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    public function feria(): BelongsTo
    {
        return $this->belongsTo(Feria::class, 'id_feria', 'id_feria');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'id_contrato', 'id_contrato');
    }
}
