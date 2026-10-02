<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ocupacion extends Model
{
    protected $table = 'ocupaciones';
    protected $primaryKey = 'id_ocupacion';
    public $timestamps = false;

    protected $fillable = [
        'id_stand',
        'id_feria',
        'id_contrato',
    ];

    /**
     * Relación con Stand
     */
    public function stand()
    {
        return $this->belongsTo(Stand::class, 'id_stand', 'id_stand');
    }

    /**
     * Relación con Contrato
     */
    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'id_contrato', 'id_contrato');
    }
}
