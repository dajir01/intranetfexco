<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresas';
    protected $primaryKey = 'id_empresa';

    public function contratos()
    {
        return $this->hasMany(Contrato::class, 'id_empresa', 'id_empresa');
    }

    public function historialCambios()
    {
        return $this->hasMany(Backedempresa::class, 'id_empresa', 'id_empresa')
            ->orderByDesc('fecha');
    }

    public static function valoresEquivalentes($valorAnterior, $valorNuevo): bool
    {
        return self::normalizarValor($valorAnterior) === self::normalizarValor($valorNuevo);
    }

    private static function normalizarValor($valor): string
    {
        if ($valor === null) {
            return '';
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        return is_scalar($valor)
            ? trim((string) $valor)
            : (json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    protected $fillable = [
        'nombre_empresa',
        'direccion',
        'telefono',
        'fax',
        'email',
        'web',
        'nit',
        'pais',
        'ciudad',
        'aniversario',
        'nr_escritura',
        'fecha_nr_escritura',
        'matricula',
        'nr_poder',
        'nr_notario',
        'fecha_nr_poder',
        'distrito',
        'cluster',
        'categoria',
        'rubro',
        'desc_producto',
        'nombre_gerente',
        'ci_gerente',
        'exp_ci_gerente',
        'fono_gerente',
        'cargo_gerente',
        'nombre_responsable',
        'ci_responsable',
        'exp_ci_responsable',
        'telefono_responsable',
        'email_representante',
        'id_tipo_representante',
        'otro_rubro',
        'subrubro',
        'ejecutivo',
        'fecha_asignacion',
        'paises_representante',
        'empresa_representante',
        'marcas_representante',
        'usuario',
        'pass',
        'pendiente_llenado',
        'id_usuario_actualizacion',
        'id_usuario_creacion',
        'es_activo',
    ];
    protected $guarded = [];
}
