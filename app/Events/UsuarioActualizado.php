<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsuarioActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public $idUsuario;
    public $accion;
    public $data;

    public function __construct($idUsuario, $accion = 'update', $data = null)
    {
        $this->idUsuario = $idUsuario;
        $this->accion = $accion;
        $this->data = $data ?? [];
    }

    public function broadcastOn(): array
    {
        return [new Channel('usuarios')];
    }

    public function broadcastAs(): string
    {
        return 'UsuarioActualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'id_usuario' => $this->idUsuario,
            'accion' => $this->accion,
            'data' => $this->data,
        ];
    }
}
