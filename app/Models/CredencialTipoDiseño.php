<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredencialTipoDiseño extends Model
{
    protected $table = 'credencial_tipo_diseno';

    public $timestamps = false;

    protected $fillable = [
        'id_feria',
        'tipo_credencial',
        'ruta',
        'nombre_archivo',
        'mime',
        'ancho',
        'alto',
        'id_usuario_creacion',
    ];

    protected $casts = [
        'id_feria' => 'integer',
        'ancho' => 'integer',
        'alto' => 'integer',
        'id_usuario_creacion' => 'integer',
    ];

    public function feria(): BelongsTo
    {
        return $this->belongsTo(Feria::class, 'id_feria', 'id_feria');
    }
}
