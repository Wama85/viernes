<?php

namespace App\Mail;

use App\Modules\Facturas\Models\Factura;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Correo al cliente con la factura: siempre lleva el PDF y el XML. */
class FacturaEnviada extends Mailable
{
    public function __construct(public Factura $factura, string $pdfBinario, string $xml)
    {
        $nombre = 'factura-' . $factura->numero;

        $this->attachData($pdfBinario, $nombre . '.pdf', ['mime' => 'application/pdf']);
        $this->attachData($xml, $nombre . '.xml', ['mime' => 'application/xml']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Factura ' . $this->factura->numero_completo . ' - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.factura');
    }
}
