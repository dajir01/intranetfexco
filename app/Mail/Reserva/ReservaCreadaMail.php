<?php

namespace App\Mail\Reserva;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservaCreadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function build(): self
    {
        $empresa = trim((string) data_get($this->data, 'empresa', 'Empresa'));
        $subject = 'Nueva Reserva en sistema - Empresa ' . ($empresa !== '' ? $empresa : 'sin nombre');

        return $this->subject($subject)
            ->view('emails.reserva.creada', ['data' => $this->data]);
    }
}
