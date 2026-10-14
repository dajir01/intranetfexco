<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NoticiaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public $idNoticia;

    public $accion;

    public $data;

    public function __construct($idNoticia, $accion = 'update', $data = null)
    {
        $this->idNoticia = $idNoticia;
        $this->accion = $accion;
        $this->data = $data ?? [];
    }

    public function broadcastOn(): array
    {
        return [new Channel('noticias')];
    }

    public function broadcastAs(): string
    {
        return 'NoticiaActualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'id_noticia' => $this->idNoticia,
            'accion' => $this->accion,
            'data' => $this->data,
        ];
    }
}
