<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReglamentoFeria extends Model
{
    protected $table = 'reglamentos_feria';

    protected $primaryKey = 'id_reglamento';

    protected $fillable = [
        'id_feria',
        'nombre_reglamento',
        'descripcion',
        'nombre_archivo',
        'ruta_archivo',
    ];

    public function feria()
    {
        return $this->belongsTo(Feria::class, 'id_feria', 'id_feria');
    }
}
