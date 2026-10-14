<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Noticia extends Model
{
    protected $table = 'noticias';

    protected $primaryKey = 'id_noticia';

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'descripcion',
        'fecha',
        'imagen',
    ];

    public function getImagenAttribute($value): ?string
    {
        if (! $value) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $filename = basename(is_string($path) ? $path : $value);

        return $filename ? '/img/noticias/'.$filename : null;
    }
}
