<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $recibo->numero_completo }}</title>
    <style>
        @page { margin: 28px 32px 85px 32px; }

        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }

        .cab td { vertical-align: top; }
        .titulo { font-size: 22px; font-weight: bold; color: #0c3082; margin-bottom: 4px; }
        .datos-empresa { font-size: 8px; color: #0c3082; line-height: 1.5; }
        .nr { border: 1px solid #ce0505; color: #ce0505; font-weight: bold; font-size: 11px;
              text-align: center; padding: 5px; margin-top: 4px; }

        .cliente { margin: 14px 0 8px 0; line-height: 1.7; font-size: 10px; }

        .items th, .items td { border: 1px solid #000; padding: 4px 5px; }
        .items th { text-align: center; background: #f1f1f1; }
        .items .sin-borde { border: none; }
        .detalle-extra { font-size: 8px; color: #555; }

        .suma { margin-top: 10px; font-size: 11px; }
        .pago { margin-top: 6px; font-size: 9px; }

        .firma { font-size: 8px; line-height: 1.7; }

        .footer { position: fixed; bottom: -60px; left: 0; right: 0; text-align: center;
                  font-size: 8px; font-style: italic; color: #0c3082; line-height: 1.6; }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>

    <div class="footer">
        {{ $empresa->direccion }}@if ($empresa->ciudad_pais) - {{ $empresa->ciudad_pais }}@endif<br>
        @if ($empresa->telefonos)Tel: {{ $empresa->telefonos }}@endif
        @if ($empresa->correo) &nbsp;·&nbsp; Correo: {{ $empresa->correo }}@endif<br>
        Página <span class="pagenum"></span>
    </div>

    <table class="cab">
        <tr>
            <td style="width: 30%;">
                @if ($logo)
                    <img src="{{ $logo }}" style="max-height: 70px; max-width: 130px;">
                @endif
                <div class="datos-empresa">
                    De: {{ $empresa->propietario }}<br>
                    {{ $empresa->direccion }}<br>
                    {{ $empresa->ciudad_pais }}
                </div>
            </td>
            <td style="width: 42%;" class="c">
                <div class="titulo">RECIBO</div>
                <div class="datos-empresa">
                    @if ($empresa->correo)Correo: {{ $empresa->correo }}<br>@endif
                    @if ($empresa->sitio_web)Web: {{ $empresa->sitio_web }}<br>@endif
                    @if ($empresa->telefonos)Telf: {{ $empresa->telefonos }}@endif
                </div>
            </td>
            <td style="width: 28%;" class="r">
                <img src="{{ $qr }}" style="width: 78px; height: 78px;">
                <div class="nr">Nr: {{ $recibo->numero_completo }}</div>
            </td>
        </tr>
    </table>

    <div class="cliente">
        <span class="b">He recibido de:</span> {{ $cliente->razon_social }}<br>
        <span class="b">NIT/CI:</span> {{ $documento }}<br>
        <span class="b">En fecha:</span> {{ \App\Support\PdfAyudas::fechaLarga($recibo->fecha_emision) }}<br>
        <span class="b">POR CONCEPTO DE</span> (según cotización {{ $cotizacion->numero_completo }}):
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 9%;">Cant.</th>
                <th>Detalle</th>
                <th style="width: 14%;">Precio U.</th>
                @if ($conDescuento)
                    <th style="width: 12%;">Desc.</th>
                @endif
                <th style="width: 15%;">Sub Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($recibo->detalles as $detalle)
                <tr>
                    <td class="c">{{ rtrim(rtrim(number_format($detalle->cantidad, 2), '0'), '.') }}</td>
                    <td>
                        {{ $detalle->descripcion }}
                        @if ($detalle->descripcion_adicional)
                            <div class="detalle-extra">{{ $detalle->descripcion_adicional }}</div>
                        @endif
                    </td>
                    <td class="c">{{ number_format($detalle->precio_unitario, 2) }}</td>
                    @if ($conDescuento)
                        <td class="c">{{ number_format($detalle->descuento, 2) }}</td>
                    @endif
                    <td class="c">{{ number_format($detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="{{ $conDescuento ? 4 : 3 }}" class="r b sin-borde" style="font-size: 11px;">TOTAL (Bs):</td>
                <td class="c b" style="font-size: 11px;">{{ number_format($recibo->monto, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="suma"><span class="b">La suma de:</span> {{ $enLetras }}</div>

    <div class="pago">
        <span class="b">Forma de pago:</span> {{ $recibo->forma_pago }}
        @if ($recibo->referencia_pago) &nbsp;·&nbsp; <span class="b">Ref.:</span> {{ $recibo->referencia_pago }} @endif
    </div>

    <table style="margin-top: 22px;">
        <tr>
            <td style="width: 55%; vertical-align: bottom;" class="firma">
                <span class="b">RECIBÍ CONFORME</span><br>
                {{ $empresa->propietario }}<br>
                CARNET: {{ $empresa->carnet }}
            </td>
            <td style="width: 45%;" class="c">
                @if ($sello)
                    <img src="{{ $sello }}" style="width: 120px; height: 120px;">
                @endif
            </td>
        </tr>
    </table>

</body>
</html>
