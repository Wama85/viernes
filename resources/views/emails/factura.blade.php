<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
        <tr>
            <td style="background:#0c3082;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold;">
                {{ config('app.name') }}
            </td>
        </tr>
        <tr>
            <td style="padding:24px;font-size:14px;line-height:1.6;">
                <p>Estimado(a) <strong>{{ $factura->cliente_razon_social }}</strong>:</p>

                <p>Le enviamos su factura <strong>{{ $factura->numero_completo }}</strong>. Adjuntamos el archivo PDF y el archivo XML.</p>

                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;font-size:14px;">
                    <tr>
                        <td style="padding:2px 16px 2px 0;color:#6b7280;">Fecha de emisión</td>
                        <td style="padding:2px 0;"><strong>{{ $factura->fecha_emision->format('d/m/Y H:i') }}</strong></td>
                    </tr>
                    <tr>
                        <td style="padding:2px 16px 2px 0;color:#6b7280;">Total</td>
                        <td style="padding:2px 0;"><strong>Bs {{ number_format($factura->monto_total, 2) }}</strong></td>
                    </tr>
                    @if ($factura->cuf)
                        <tr>
                            <td style="padding:2px 16px 2px 0;color:#6b7280;vertical-align:top;">CUF</td>
                            <td style="padding:2px 0;word-break:break-all;font-size:12px;">{{ $factura->cuf }}</td>
                        </tr>
                    @endif
                </table>

                @if ($factura->esDePrueba())
                    <p style="padding:10px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;color:#1e40af;">
                        Documento emitido en el ambiente de pruebas del SIN: no tiene valor legal.
                    </p>
                @endif

                <p>Cualquier consulta puede responder a este correo.</p>

                <p style="margin-top:24px;">Saludos cordiales,<br><strong>{{ config('app.name') }}</strong></p>
            </td>
        </tr>
    </table>
</body>
</html>
