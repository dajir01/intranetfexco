<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Recibo;
use Illuminate\Support\Facades\DB;

class ReciboNumberService
{
    public function generarParaPago(Pago $pago, int $userId): Recibo
    {
        return DB::transaction(function () use ($pago, $userId) {
            $existente = Recibo::where('id_pago', $pago->id)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $existente;
            }

            $anio = (int) now()->format('Y');
            $yy = now()->format('y');

            $ultimoRecibo = Recibo::where('anio', $anio)
                ->lockForUpdate()
                ->orderByDesc('correlativo')
                ->first();

            $siguienteCorrelativo = ($ultimoRecibo?->correlativo ?? 0) + 1;

            return Recibo::create([
                'numero_recibo' => $this->formatearNumero($siguienteCorrelativo, $yy),
                'anio' => $anio,
                'correlativo' => $siguienteCorrelativo,
                'tipo_pago' => (int) $pago->tipo_pago,
                'user_id' => $userId,
                'id_pago' => (int) $pago->id,
            ]);
        }, 3);
    }

    public function formatearNumero(int $correlativo, string $yy): string
    {
        return sprintf('%06d/%s', $correlativo, $yy);
    }
}
