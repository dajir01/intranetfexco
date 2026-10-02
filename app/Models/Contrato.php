<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
    protected $table = 'contrato';
    protected $primaryKey = 'id_contrato';
    public $timestamps = false;
    protected $fillable = [
        'id_empresa',
        'total_stands',
        'stand_1',
        'stand_2',
        'stand_3',
        'stand_4',
        'stand_5',
        'stand_6',
        'stand_7',
        'stand_8',
        'stand_9',
        'stand_10',
        'stand_11',
        'stand_12',
        'metraje_total',
        'precio_unit',
        'descuento',
        'precio_total',
        'tipo_desc',
        'monto_inicial',
        'porcentaje_inicial',
        'fecha_inicial',
        'monto_final',
        'porcentaje_final',
        'fecha_final',
        'tipo_pago',
        'nro_cheque',
        'banco',
        'nro_credenciales',
        'potencia',
        'nro_entradas',
        'productos',
        'perfil_visitante',
        'pais_principal',
        'marca_principal',
        'paises_secundarios',
        'marcas_secundarios',
        'habilitado_feria',
        'id_feria',
        'codigo_contrato',
        'clave',
        'envio_mail',
        'observaciones',
        'fecha_realizacion',
        'id_usuario_contrato',
        'id_usuario_reserva',
        'fecha_creacion_reserva',
        'tiempo_reserva',
        'fecha_fin_reserva',
        'id_carta',
        'estado_reserva',
        'entrega',
        'pago',
        'medio_comunicacion',
        'como_entero',
        'tipo_expositor'

    ];
    protected $guarded = [];

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Relación con Feria
     */
    public function feria()
    {
        return $this->belongsTo(Feria::class, 'id_feria', 'id_feria');
    }

    /**
     * Relación con Usuario que reservó (usuarios_reserva)
     */
    public function usuarios_reserva()
    {
        return $this->belongsTo(Usuario::class, 'usuarioCreador', 'id_usuario');
    }

    /**
     * Relación con Usuario que generó (usuarios_generacion)
     */
    public function usuarios_generacion()
    {
        return $this->belongsTo(Usuario::class, 'usuarioGenerador', 'id_usuario');
    }

    /**
     * Relación con Concepto de stand/precio
     */
    public function concepto()
    {
        return $this->hasOne(Concepto::class, 'id_contrato', 'id_contrato');
    }
}
