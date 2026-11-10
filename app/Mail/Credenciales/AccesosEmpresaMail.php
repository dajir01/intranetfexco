<?php

namespace App\Mail\Credenciales;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccesosEmpresaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data) {}

    public function build(): self
    {
        $empresa = trim(preg_replace('/[\r\n]+/', ' ', (string) ($this->data['nombre_empresa'] ?? '')));

        return $this
            ->subject("Accesos al Sistema de Acreditación - {$empresa}")
            ->view('emails.credenciales.accesos-empresa', [
                'data' => $this->data,
                'title' => 'Accesos al Sistema de Acreditación',
            ]);
    }
}
