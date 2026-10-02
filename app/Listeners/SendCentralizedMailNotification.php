<?php

namespace App\Listeners;

use App\Events\ContratoCreado;
use App\Events\PagoRegistrado;
use App\Events\ReservaCreada;
use App\Events\ActualizacionDatos;
use App\Services\MailNotificationService;
use Illuminate\Support\Facades\Log;

class SendCentralizedMailNotification
{
    public function __construct(protected MailNotificationService $mailNotificationService)
    {
    }

    public function handle(object $event): void
    {
        $eventName = match (true) {
            $event instanceof ReservaCreada => 'reserva_creada',
            $event instanceof ContratoCreado => 'contrato_creado',
            $event instanceof PagoRegistrado => 'pago_registrado',
            $event instanceof ActualizacionDatos => 'actualizacion_datos',
            default => null,
        };

        if ($eventName === null) {
            return;
        }

        try {
            $this->mailNotificationService->sendImmediately($eventName, $event->payload ?? []);
        } catch (\Throwable $exception) {
            Log::error('No se pudo enviar la notificación inmediata', [
                'event' => $eventName,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
