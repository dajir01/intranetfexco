<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContratoActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public $idFeria;
    public $idContrato;
    public $accion; // 'create', 'update', 'delete'
    public $data;

    /**
     * Create a new event instance.
     */
    public function __construct($idFeria, $idContrato, $accion = 'create', $data = null)
    {
        $this->idFeria = $idFeria;
        $this->idContrato = $idContrato;
        $this->accion = $accion;
        $this->data = $data ?? [];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("contratos.feria.{$this->idFeria}"),
        ];
    }

    /**
     * Get the name of the event as broadcasted.
     */
    public function broadcastAs(): string
    {
        return 'ContratoActualizado';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id_contrato' => $this->idContrato,
            'id_feria' => $this->idFeria,
            'accion' => $this->accion,
            'data' => $this->data,
        ];
    }
}
