<?php

namespace App\Mail\Bancos;

use App\Models\BankRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ResumenPorCorreo extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, BankRequest>  $solicitudes
     */
    public function __construct(public Collection $solicitudes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Estado de sus solicitudes de banco');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bancos.resumen-por-correo');
    }
}
