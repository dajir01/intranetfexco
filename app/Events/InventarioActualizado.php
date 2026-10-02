<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventarioActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public $tipo;
    public $id;
    public $accion;
    public $data;

    public function __construct($tipo, $id, $accion = 'update', $data = null)
    {
        $this->tipo = $tipo; // 'ingreso', 'movimiento', 'baja', etc.
        $this->id = $id;
        $this->accion = $accion;
        $this->data = $data ?? [];
    }

    public function broadcastOn(): array
    {
        return [new Channel('inventario')];
    }

    public function broadcastAs(): string
    {
        return 'InventarioActualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'tipo' => $this->tipo,
            'id' => $this->id,
            'accion' => $this->accion,
            'data' => $this->data,
        ];
    }
}
