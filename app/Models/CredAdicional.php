<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredAdicional extends Model
{
    protected $table = 'cred_adicionales';

    protected $primaryKey = 'id_adic';

    protected $fillable = [
        'feria',
        'id_empresa',
        'contrato',
        'cant_adic',
        'fecha_adic',
        'obs',
    ];

    protected $casts = [
        'cant_adic' => 'integer',
        'fecha_adic' => 'date',
    ];
}
