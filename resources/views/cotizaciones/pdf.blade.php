<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $cotizacion->numero_completo }}</title>
    <style>
        @page { margin: 28px 32px 85px 32px; }

        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        .azul { color: #0c3082; }
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
        .items tr { page-break-inside: avoid; }
        .detalle-extra { font-size: 8px; color: #555; }

        /* Encabezado de grupo (categoría) */
        .items td.grupo { background: #e6ecf7; color: #0c3082; font-weight: bold; font-size: 10px; }
        .items td.sangria { padding-left: 14px; }
        .items td.subtotal-grupo { font-size: 9px; font-style: italic; color: #333; }

        .son { margin-top: 10px; font-size: 11px; }
        .validez { margin-top: 6px; font-size: 9px; color: #0c3082; font-weight: bold; }
        .obs { margin-top: 8px; font-size: 9px; }

        .pie td { vertical-align: top; }
        .firma { font-size: 8px; line-height: 1.7; }

        .footer { position: fixed; bottom: -60px; left: 0; right: 0; text-align: center;
                  font-size: 8px; font-style: italic; color: #0c3082; line-height: 1.6; }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>

    @php
        // Pon true para mostrar una fila de subtotal al final de cada grupo.
        $subtotalPorGrupo = false;

        // Total de columnas de la tabla (Cant., Detalle, Precio U., [Desc.], Sub Total).
        $columnas = $conDescuento ? 5 : 4;

        // Título del grupo: la categoría real del producto; si no la hay, la descripción de la línea.
        $tituloDe = fn ($d) => mb_strtoupper(trim((string) ($d->producto?->categoria?->nombre_categoria ?: $d->descripcion)));

        // Agrupa las líneas por categoría, respetando el orden de aparición en la cotización.
        $grupos = $cotizacion->detalles->values()->groupBy($tituloDe);

        // Texto principal y texto pequeño de cada línea. Si la descripción repite el título
        // del grupo, se muestra directamente el detalle específico.
        $textoLinea = function ($d, $tituloGrupo) {
            $desc = trim((string) $d->descripcion);
            $adic = trim((string) $d->descripcion_adicional);

            if ($adic !== '' && mb_strtoupper($desc) === $tituloGrupo) {
                return [$adic, null];
            }

            return [$desc, $adic !== '' ? $adic : null];
        };
    @endphp

    {{-- Pie de página --}}
    <div class="footer">
        {{ $empresa->direccion }}@if ($empresa->ciudad_pais) - {{ $empresa->ciudad_pais }}@endif<br>
        @if ($empresa->telefonos)Tel: {{ $empresa->telefonos }}@endif
        @if ($empresa->correo) &nbsp;·&nbsp; Correo: {{ $empresa->correo }}@endif<br>
        Página <span class="pagenum"></span>
    </div>

    {{-- Encabezado --}}
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
                <div class="titulo">COTIZACIÓN</div>
                <div class="datos-empresa">
                    @if ($empresa->correo)Correo: {{ $empresa->correo }}<br>@endif
                    @if ($empresa->sitio_web)Web: {{ $empresa->sitio_web }}<br>@endif
                    @if ($empresa->telefonos)Telf: {{ $empresa->telefonos }}@endif
                </div>
            </td>
            <td style="width: 28%;" class="r">
                <img src="{{ $qr }}" style="width: 78px; height: 78px;">
                <div class="nr">Nr: {{ $cotizacion->numero_completo }}</div>
            </td>
        </tr>
    </table>

    {{-- Cliente --}}
    <div class="cliente">
        <span class="b">INSTITUCIÓN:</span> {{ $cliente->razon_social }}<br>
        <span class="b">Fecha:</span> {{ \App\Support\PdfAyudas::fechaLarga($cotizacion->fecha_emision) }}<br>
         <span class="b">NIT/CI:</span> {{ $documento }}
    </div>

    {{-- Ítems --}}
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
            @foreach ($grupos as $tituloGrupo => $lineas)
                @php
                    // Una categoría con una sola línea cuya descripción ya es el título no necesita encabezado.
                    $conEncabezado = $lineas->count() > 1
                        || mb_strtoupper(trim((string) $lineas->first()->descripcion)) !== $tituloGrupo;
                @endphp

                {{-- Título del grupo: sale una sola vez --}}
                @if ($conEncabezado)
                    <tr>
                        <td colspan="{{ $columnas }}" class="grupo">{{ $tituloGrupo }}</td>
                    </tr>
                @endif

                @foreach ($lineas as $detalle)
                    @php [$principal, $extra] = $textoLinea($detalle, $tituloGrupo); @endphp
                    <tr>
                        <td class="c">{{ rtrim(rtrim(number_format($detalle->cantidad, 2), '0'), '.') }}</td>
                        <td @class(['sangria' => $conEncabezado])>
                            {{ $principal }}
                            @if ($extra)
                                <div class="detalle-extra">{{ $extra }}</div>
                            @endif
                        </td>
                        <td class="c">{{ number_format($detalle->precio_unitario, 2) }}</td>
                        @if ($conDescuento)
                            <td class="c">{{ number_format($detalle->descuento, 2) }}</td>
                        @endif
                        <td class="c">{{ number_format($detalle->subtotal, 2) }}</td>
                    </tr>
                @endforeach

                {{-- Subtotal del grupo (opcional) --}}
                @if ($conEncabezado && $subtotalPorGrupo)
                    <tr>
                        <td colspan="{{ $columnas - 1 }}" class="r subtotal-grupo">Subtotal {{ $tituloGrupo }}:</td>
                        <td class="c subtotal-grupo">{{ number_format($lineas->sum('subtotal'), 2) }}</td>
                    </tr>
                @endif
            @endforeach

            <tr>
                <td colspan="{{ $conDescuento ? 4 : 3 }}" class="r b sin-borde" style="font-size: 11px;">TOTAL (Bs):</td>
                <td class="c b" style="font-size: 11px;">{{ number_format($cotizacion->total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="son"><span class="b">SON:</span> {{ $enLetras }}</div>

    <div class="validez">
        Cotización válida hasta: {{ $cotizacion->fecha_vencimiento->format('d/m/Y H:i') }}
    </div>

    @if ($cotizacion->observaciones)
        <div class="obs"><span class="b">Observaciones:</span> {{ $cotizacion->observaciones }}</div>
    @endif

    {{-- Firma, formas de pago y sello --}}
    <table class="pie" style="margin-top: 18px;">
        <tr>
            <td style="width: 62%;" class="firma">
                <span class="b">PROPIETARIO:</span> {{ $empresa->propietario }}<br>
                <span class="b">CARNET:</span> {{ $empresa->carnet }}<br>
                @if ($empresa->formas_pago)
                    <span class="b">FORMAS DE PAGO:</span> {{ $empresa->formas_pago }}<br>
                @endif
                @if ($empresa->cuentas_bancarias)
                    <span class="b">CUENTAS:</span><br>{!! nl2br(e($empresa->cuentas_bancarias)) !!}
                @endif
            </td>
            <td style="width: 38%;" class="c">
                @if ($sello)
                    <img src="{{ $sello }}" style="width: 120px; height: 120px;">
                @endif
            </td>
        </tr>
    </table>

</body>
</html>
