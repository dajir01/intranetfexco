<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeriaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public $idFeria;
    public $accion;
    public $data;

    public function __construct($idFeria, $accion = 'update', $data = null)
    {
        $this->idFeria = $idFeria;
        $this->accion = $accion;
        $this->data = $data ?? [];
    }

    public function broadcastOn(): array
    {
        return [new Channel('ferias')];
    }

    public function broadcastAs(): string
    {
        return 'FeriaActualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'id_feria' => $this->idFeria,
            'accion' => $this->accion,
            'data' => $this->data,
        ];
    }
}
