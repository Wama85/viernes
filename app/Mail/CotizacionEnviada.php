<?php

namespace App\Mail;

use App\Modules\Cotizaciones\Models\Cotizacion;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CotizacionEnviada extends Mailable
{
    public function __construct(public Cotizacion $cotizacion, string $pdfBinario)
    {
        $this->attachData(
            $pdfBinario,
            $cotizacion->numero_completo . '.pdf',
            ['mime' => 'application/pdf']
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cotización ' . $this->cotizacion->numero_completo . ' - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.cotizacion');
    }
}
