<?php

namespace App\Mail\Contrato;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContratoCreadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function build(): self
    {
        $empresa = trim((string) ($this->data['empresa'] ?? ''));
        $subject = 'Nuevo contrato generado - Empresa ' . ($empresa !== '' ? $empresa : 'sin nombre');
        $this->subject($subject);

        return $this->view('emails.contrato.creado', ['data' => $this->data]);
    }
}
