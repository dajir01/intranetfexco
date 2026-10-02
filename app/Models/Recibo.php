<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recibo extends Model
{
    protected $table = 'recibos';
    protected $primaryKey = 'id_recibo';
    public $incrementing = true;

    protected $fillable = [
        'numero_recibo',
        'anio',
        'correlativo',
        'tipo_pago',
        'user_id',
        'id_pago',
    ];

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'id_pago', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'user_id', 'id_usuario');
    }
}
