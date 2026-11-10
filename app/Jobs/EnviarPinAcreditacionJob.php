<?php

namespace App\Jobs;

use App\Models\CredencialPin;
use App\Support\PinAcreditacionCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EnviarPinAcreditacionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $idPin,
        public string $correo,
        public string $pin,
        public string $nombreFeria,
        public string $nombreEmpresa,
        public string $tipoCredencial,
        public ?int $idUsuario = null,
    ) {}

    public function handle(): void
    {
        Mail::html(
            PinAcreditacionCorreo::html($this->nombreFeria, $this->nombreEmpresa, [[
                'pin' => $this->pin,
                'tipo' => $this->tipoCredencial,
            ]]),
            fn ($message) => $message->to($this->correo)->subject('PIN de autoacreditación'),
        );

        CredencialPin::query()->whereKey($this->idPin)->update([
            'estado' => 'enviado',
            'email_destinatario' => $this->correo,
            'ultimo_canal_envio' => 'email',
            'ultimo_envio_at' => now(),
        ]);
    }
}
