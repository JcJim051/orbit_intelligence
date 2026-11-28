<?php

namespace App\Mail\Bancos;

use App\Models\BankRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudRecibida extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BankRequest $solicitud) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recibimos su solicitud '.$this->solicitud->numero_seguimiento);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bancos.solicitud-recibida');
    }
}
