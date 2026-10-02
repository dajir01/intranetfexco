<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificacionAdmin extends Model
{
    protected $table = 'admin_notificaciones';

    protected $fillable = [
        'id_usuario',
        'tipo',
        'titulo',
        'mensaje',
        'payload',
        'leida',
    ];

    protected $casts = [
        'payload' => 'array',
        'leida' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
