<?php

namespace App\Jobs;

use App\Models\CredencialPin;
use App\Support\PinAcreditacionCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class EnviarPinesAcreditacionEmpresaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public array $idsPines,
        public string $correo,
        public string $nombreFeria,
        public string $nombreEmpresa,
        public ?int $idUsuario = null,
        public string $loteUuid = '',
    ) {}

    public function handle(): void
    {
        $pines = CredencialPin::query()
            ->whereIn('id', $this->idsPines)
            ->whereIn('estado', ['disponible', 'enviado'])
            ->orderBy('id')
            ->get();

        if ($pines->isEmpty()) {
            return;
        }

        Mail::html(
            PinAcreditacionCorreo::html($this->nombreFeria, $this->nombreEmpresa, $pines->map(fn (CredencialPin $pin) => [
                'pin' => Crypt::decryptString($pin->pin_encrypted),
                'tipo' => $pin->tipo_credencial,
            ])->all()),
            fn ($message) => $message->to($this->correo)->subject('PINs de autoacreditación'),
        );

        CredencialPin::query()->whereIn('id', $pines->pluck('id'))->update([
            'estado' => 'enviado',
            'email_destinatario' => $this->correo,
            'ultimo_canal_envio' => 'email',
            'ultimo_envio_at' => now(),
        ]);
    }
}
