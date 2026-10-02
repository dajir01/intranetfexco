<?php

namespace App\Mail\Pago;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PagoRegistradoMail extends Mailable
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
        $subject = 'Comprobante de Pago - Empresa ' . ($empresa !== '' ? $empresa : 'sin nombre');

        return $this->subject($subject)
            ->view('emails.pago.registrado', ['data' => $this->data]);
    }
}
