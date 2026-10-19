<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $factura->numero ?? 'S/N' }}</title>
    <style>
        @page { margin: 28px 32px 60px 32px; }

        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }

        .marca { position: fixed; top: 38%; left: 0; width: 100%; text-align: center;
                 font-size: 54px; font-weight: bold; color: #e3e3e3;
                 transform: rotate(-30deg); line-height: 1.15; }

        .cab td { vertical-align: top; }
        .titulo { font-size: 22px; font-weight: bold; text-align: center; }
        .subtitulo { font-size: 10px; font-weight: bold; text-align: center; margin-top: 2px; }
        .emisor { text-align: center; line-height: 1.5; font-size: 9px; }
        .emisor .razon { font-size: 11px; font-weight: bold; }
        .caja { line-height: 1.7; font-size: 9px; }
        .cuf { word-break: break-all; font-size: 8px; }

        .aviso { margin: 8px 0 0 0; padding: 5px; border: 1px solid #ce0505; color: #ce0505;
                 font-weight: bold; font-size: 10px; text-align: center; }

        .linea { border-top: 1px solid #000; margin: 8px 0; }
        .cliente td { padding: 2px 0; font-size: 9px; }

        .items th, .items td { border: 1px solid #000; padding: 3px 4px; }
        .items th { text-align: center; background: #e9e9e9; font-size: 8px; }
        .items td { font-size: 8.5px; }
        .items tr { page-break-inside: avoid; }

        .totales td { padding: 3px 5px; font-size: 9px; }
        .totales .caja-tot td { border: 1px solid #000; }
        .son { font-size: 10px; margin-top: 8px; }

        .leyendas { text-align: center; margin-top: 10px; line-height: 1.5; font-size: 8px; }
        .leyendas .l1 { font-weight: bold; font-size: 8.5px; }
        .leyendas .l3 { font-size: 7.5px; }
    </style>
</head>
<body>

    @if ($sinValorLegal)
        <div class="marca">SIN VALOR<br>LEGAL</div>
    @endif

    {{-- Encabezado: emisor | factura | datos de autorización --}}
    <table class="cab">
        <tr>
            <td style="width: 36%; text-align: left;">
                @if ($logo)
                    <div class="c"><img src="{{ $logo }}" style="max-height: 55px; max-width: 120px;"></div>
                @endif
                <div class="emisor">
                    <div class="razon">{{ $emisor['razon_social'] }}</div>
                    Casa Matriz<br>

                    {{ $emisor['direccion'] }}<br>

                    {{ $emisor['municipio'] }}
                </div>
            </td>
            <td style="width: 26%;">
                <div class="titulo">FACTURA</div>
                <div class="subtitulo">CON DERECHO A CRÉDITO FISCAL</div>
            </td>
              <td style="width: 38%; text-align: right;" class="caja">
                <span class="b">NIT:</span> {{ $emisor['nit'] }}<br>
                <span class="b">FACTURA Nº:</span> {{ $factura->numero ?? 'S/N' }}<br>
                <span class="b">CÓD. AUTORIZACIÓN:</span>
                <div class="cuf">
                    @if ($factura->cuf)
                        @foreach (str_split($factura->cuf, 32) as $parte)
                            {{ $parte }}<br>
                        @endforeach
                    @else
                        (se asigna al validarse en el SIN)
                    @endif
                </div>
            </td>
        </tr>
    </table>

    @if ($sinValorLegal)
        <div class="aviso">
            DOCUMENTO INTERNO – SIN VALOR LEGAL
            <div style="font-weight: normal; font-size: 9px;">
                @if ($validada)
                    Validado en el ambiente de pruebas del SIN. No sirve para crédito fiscal.
                @else
                    Todavía no fue validado por el SIN. No sirve para crédito fiscal.
                @endif
            </div>
        </div>
    @endif

    <div class="linea"></div>

    {{-- Cliente --}}
    <table class="cliente">
        <tr>
            <td style="width: 60%;"><span class="b">NOMBRE/RAZÓN SOCIAL:</span> {{ $factura->cliente_razon_social }}</td>
            <td><span class="b">FECHA Y HORA DE EMISIÓN:</span> {{ $factura->fecha_emision->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td><span class="b">NIT/CI/CEX:</span> {{ $documento }}</td>
            <td><span class="b">COD. CLIENTE:</span> {{ $factura->cliente_documento }}</td>
        </tr>
    </table>

    <div class="linea"></div>

    {{-- Detalle --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 9%;">CÓDIGO PRODUCTO / SERVICIO</th>
                <th style="width: 8%;">CANTIDAD</th>
                <th style="width: 12%;">UNIDAD DE MEDIDA</th>
                <th>DESCRIPCIÓN</th>
                <th style="width: 11%;">PRECIO UNITARIO</th>
                <th style="width: 10%;">DESCUENTO</th>
                <th style="width: 12%;">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineas as $l)
                <tr>
                    <td class="c">{{ $l['codigo'] }}</td>
                    <td class="c">{{ rtrim(rtrim(number_format($l['cantidad'], 2), '0'), '.') }}</td>
                    <td class="c">{{ $l['unidad'] }}</td>
                    <td>{{ $l['descripcion'] }}</td>
                    <td class="r">{{ number_format($l['precio'], 2) }}</td>
                    <td class="r">{{ number_format($l['descuento'], 2) }}</td>
                    <td class="r">{{ number_format($l['subtotal'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totales + QR --}}
    <table style="margin-top: 8px;">
        <tr>
            <td style="width: 58%; vertical-align: top;">
                <div class="son"><span class="b">Son:</span> {{ $enLetras }}</div>
            </td>
            <td style="width: 42%; vertical-align: top;">
                <table class="totales">
                    <tr><td class="r b">SUBTOTAL Bs</td><td class="r" style="width: 28%;">{{ number_format($subtotalBruto, 2) }}</td></tr>
                    <tr><td class="r b">DESCUENTO Bs</td><td class="r">{{ number_format($descuentoTotal, 2) }}</td></tr>
                    <tr><td class="r b">TOTAL Bs</td><td class="r">{{ number_format($total, 2) }}</td></tr>
                    <tr><td class="r b">MONTO GIFT CARD Bs</td><td class="r">0.00</td></tr>
                    <tr class="caja-tot"><td class="r b">MONTO A PAGAR Bs</td><td class="r b">{{ number_format($total, 2) }}</td></tr>
                    <tr class="caja-tot"><td class="r b">IMPORTE BASE CRÉDITO FISCAL Bs</td><td class="r b">{{ number_format($total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 6px;">
        <tr>
            <td style="width: 78%; vertical-align: bottom;">
                <div class="leyendas" style="text-align: left;">
                    <div class="l1">“ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO DE ÉSTA SERÁ SANCIONADO DE ACUERDO A LA LEY”</div>
                    @if ($factura->leyenda)
                        <div>{{ $factura->leyenda }}</div>
                    @endif
                    <div class="l3">“Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea”</div>
                </div>
            </td>
            <td style="width: 22%;" class="r">
                <img src="{{ $qr }}" style="width: 95px; height: 95px;">
            </td>
        </tr>
    </table>

</body>
</html>
