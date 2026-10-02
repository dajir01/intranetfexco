<?php

namespace App\Mail\Contrato;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActualizacionDatosMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data = [])
    {
    }

    public function build(): self
    {
        $empresa = trim((string) data_get($this->data, 'empresa', 'Empresa'));

        return $this
            ->subject('Actualización de Datos - Empresa ' . ($empresa !== '' ? $empresa : 'sin nombre'))
            ->view('emails.contrato.actualizacion-datos', ['data' => $this->data]);
    }
}
