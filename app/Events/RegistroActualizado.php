<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RegistroActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $registro;
    public string $accion;
    public int $idFeria;

    public function __construct(array $registro, string $accion, int $idFeria)
    {
        $this->registro = $registro;
        $this->accion = $accion;
        $this->idFeria = $idFeria;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("pagos.feria.{$this->idFeria}");
    }

    public function broadcastAs(): string
    {
        return 'RegistroActualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'registro' => $this->registro,
            'accion' => $this->accion,
            'id_feria' => $this->idFeria,
        ];
    }
}
