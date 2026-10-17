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
                <p>Estimado(a) <strong>{{ $cotizacion->cliente->razon_social }}</strong>:</p>

                <p>Le enviamos adjunta la cotización <strong>{{ $cotizacion->numero_completo }}</strong>.</p>

                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;font-size:14px;">
                    <tr>
                        <td style="padding:2px 16px 2px 0;color:#6b7280;">Total</td>
                        <td style="padding:2px 0;"><strong>Bs {{ number_format($cotizacion->total, 2) }}</strong></td>
                    </tr>
                    <tr>
                        <td style="padding:2px 16px 2px 0;color:#6b7280;">Válida hasta</td>
                        <td style="padding:2px 0;"><strong>{{ $cotizacion->fecha_vencimiento->format('d/m/Y H:i') }}</strong></td>
                    </tr>
                </table>

                <p>Cualquier consulta puede responder a este correo.</p>

                <p style="margin-top:24px;">Saludos cordiales,<br><strong>{{ config('app.name') }}</strong></p>
            </td>
        </tr>
    </table>
</body>
</html>
