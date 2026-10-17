<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Cotización {{ $cotizacion->numero_completo }}
            </h2>
            <x-estado-cotizacion :cotizacion="$cotizacion" />
        </div>
    </x-slot>

    @php
        $esAdmin = auth()->user()->id_rol == 1;
        $editable = $cotizacion->puedeEditarPor(auth()->user());
        $cliente = $cotizacion->cliente;
        $recibos = $cotizacion->recibos()->with('emisor')->orderBy('anio')->orderBy('numero')->get();
        $facturas = $cotizacion->facturas()->with('emisor')->orderBy('id_factura')->get();
        $recibido = $cotizacion->montoRecibido();
        $facturado = $cotizacion->montoFacturado();
        $saldo = $cotizacion->saldo();
        $puedeEmitir = $cotizacion->puedeEmitirRecibo();
        $reciboAnulado = \App\Modules\Recibos\Models\Recibo::ANULADO;
        $facturaAnulada = \App\Modules\Facturas\Models\Factura::ANULADA;

        // Envío al cliente
        $puedeEnviar = $cotizacion->estado === 'APROBADA' && $cotizacion->fecha_vencimiento->isFuture();
        $correosEnvio = $cliente->correos->pluck('correo')
            ->filter(fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL))
            ->unique()->values();
        $whatsappEnvio = \App\Support\Whatsapp::primero($cliente->telefonos->pluck('telefono'));
    @endphp

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 rounded bg-red-100 text-red-800">{{ session('error') }}</div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-6 text-sm text-gray-700 dark:text-gray-300">

                {{-- Cliente --}}
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Cliente</h3>
                    <p class="font-medium">{{ $cliente->razon_social }}</p>
                    <p>
                        {{ $cliente->tipoDocumento->nombre_tipo_documento }}:
                        {{ $cliente->documento_identidad }}{{ $cliente->complemento ? '-' . $cliente->complemento : '' }}
                    </p>
                    @if ($cliente->nombre_responsable)<p>Responsable: {{ $cliente->nombre_responsable }}</p>@endif
                    @if ($cliente->direccion)<p>{{ $cliente->direccion }}</p>@endif
                    @if ($cliente->correos->isNotEmpty())
                        <p>Correos: {{ $cliente->correos->pluck('correo')->join(', ') }}</p>
                    @endif
                    @if ($cliente->telefonos->isNotEmpty())
                        <p>Teléfonos: {{ $cliente->telefonos->pluck('telefono')->join(', ') }}</p>
                    @endif
                </div>

                {{-- Datos del documento --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <p>Emisión: {{ $cotizacion->fecha_emision->format('d/m/Y H:i') }}</p>
                    <p>Vence: {{ $cotizacion->fecha_vencimiento->format('d/m/Y H:i') }}</p>
                    <p>Elaborada por: {{ $cotizacion->creador->nombre }}</p>
                    @if ($cotizacion->aprobador)
                        <p>Aprobada por: {{ $cotizacion->aprobador->nombre }} ({{ $cotizacion->fecha_aprobacion->format('d/m/Y H:i') }})</p>
                    @endif
                    @if ($cotizacion->fueEnviada())
                        <p>Enviada al cliente: {{ $cotizacion->fecha_envio->format('d/m/Y H:i') }}</p>
                    @endif
                </div>

                {{-- Líneas --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                            <tr>
                                <th class="px-3 py-2">Descripción</th>
                                <th class="px-3 py-2">Unidad</th>
                                <th class="px-3 py-2 text-right">Cant.</th>
                                <th class="px-3 py-2 text-right">Precio</th>
                                <th class="px-3 py-2 text-right">Desc.</th>
                                <th class="px-3 py-2 text-right">Subtotal</th>
                                <th class="px-3 py-2">Documento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cotizacion->detalles as $detalle)
                                @php
                                    $enRecibos = $detalle->recibos->where('estado', '!=', $reciboAnulado);
                                    $enFacturas = $detalle->facturas->where('estado', '!=', $facturaAnulada);
                                @endphp
                                <tr class="border-t dark:border-gray-700">
                                    <td class="px-3 py-2">
                                        {{ $detalle->descripcion }}
                                        @if ($detalle->descripcion_adicional)
                                            <div class="text-xs text-gray-500">{{ $detalle->descripcion_adicional }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ $detalle->unidadMedida->nombre_unidad_medida }}</td>
                                    <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format($detalle->cantidad, 2), '0'), '.') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($detalle->precio_unitario, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($detalle->descuento, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($detalle->subtotal, 2) }}</td>
                                    <td class="px-3 py-2">
                                        @foreach ($enRecibos as $r)
                                            <a href="{{ route('recibos.show', $r) }}" class="text-indigo-600 hover:underline">{{ $r->numero_completo }}</a>@if (! $loop->last || $enFacturas->isNotEmpty()), @endif
                                        @endforeach
                                        @foreach ($enFacturas as $f)
                                            <a href="{{ route('facturas.show', $f) }}" class="text-indigo-600 hover:underline">{{ $f->numero_completo }}</a>@if (! $loop->last), @endif
                                        @endforeach
                                        @if ($enRecibos->isEmpty() && $enFacturas->isEmpty())
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t dark:border-gray-700 font-bold">
                                <td colspan="5" class="px-3 py-2 text-right">TOTAL (Bs)</td>
                                <td class="px-3 py-2 text-right">{{ number_format($cotizacion->total, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if ($cotizacion->observaciones)
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Observaciones</h3>
                        <p class="whitespace-pre-line">{{ $cotizacion->observaciones }}</p>
                    </div>
                @endif
            </div>

            {{-- Envío al cliente --}}
            @if ($puedeEnviar)
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Enviar al cliente</h3>
                        @if (! $cotizacion->fueEnviada())
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Es necesario para poder emitir recibos o facturas.
                            </span>
                        @else
                            <span class="text-xs text-gray-500 dark:text-gray-400">Ya fue enviada; puedes reenviarla.</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-start gap-4">

                        {{-- Correo --}}
                        @if ($correosEnvio->isNotEmpty())
                            <form method="POST" action="{{ route('cotizaciones.enviar.correo', $cotizacion) }}"
                                  onsubmit="var b=this.querySelector('button'); b.disabled=true; b.textContent='Enviando...';">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-[#0c3082] text-white rounded-md hover:bg-[#0a2668]">
                                    Enviar por correo
                                </button>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $correosEnvio->join(', ') }}</p>
                            </form>
                        @endif

                        {{-- WhatsApp --}}
                        @if ($whatsappEnvio)
                            <form method="POST" action="{{ route('cotizaciones.enviar.whatsapp', $cotizacion) }}"
                                  target="_blank" onsubmit="setTimeout(function () { location.reload(); }, 1500);">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    Enviar por WhatsApp
                                </button>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">+{{ $whatsappEnvio }}</p>
                            </form>
                        @endif

                        {{-- Manual: PDF para enviarlo por otro medio --}}
                        @if (! $cotizacion->fueEnviada())
                            <form method="POST" action="{{ route('cotizaciones.enviar.manual', $cotizacion) }}"
                                  target="_blank" onsubmit="setTimeout(function () { location.reload(); }, 1500);">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700">
                                    Descargar PDF y marcar como enviada
                                </button>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Para enviarla tú por otro medio.</p>
                            </form>
                        @endif
                    </div>

                    @if ($correosEnvio->isEmpty() && ! $whatsappEnvio)
                        <p class="text-xs text-yellow-700 dark:text-yellow-400">
                            El cliente no tiene correo ni celular válido registrado. Descarga el PDF y envíalo manualmente,
                            o completa sus datos en Clientes.
                        </p>
                    @endif
                </div>
            @endif

            {{-- Totales --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 text-sm text-gray-700 dark:text-gray-300">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <p>Total: <span class="font-semibold">Bs {{ number_format($cotizacion->total, 2) }}</span></p>
                    <p>En recibos: <span class="font-semibold">Bs {{ number_format($recibido, 2) }}</span></p>
                    <p>En facturas: <span class="font-semibold">Bs {{ number_format($facturado, 2) }}</span></p>
                    <p>Sin documento: <span class="font-semibold">Bs {{ number_format($saldo, 2) }}</span></p>
                </div>
                <p class="text-xs text-gray-500 mt-2">Incluye los documentos pendientes de aprobación y excluye los anulados.</p>
            </div>

            {{-- Recibos --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Recibos</h3>
                    @if ($puedeEmitir)
                        <a href="{{ route('recibos.create', $cotizacion) }}"
                           class="px-3 py-1 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Emitir recibo</a>
                    @endif
                </div>

                @if ($recibos->isEmpty())
                    <p class="text-gray-500">Esta cotización aún no tiene recibos.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                                <tr>
                                    <th class="px-3 py-2">Recibo</th>
                                    <th class="px-3 py-2">Fecha</th>
                                    <th class="px-3 py-2">Forma de pago</th>
                                    <th class="px-3 py-2 text-right">Monto</th>
                                    <th class="px-3 py-2">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recibos as $recibo)
                                    <tr class="border-t dark:border-gray-700">
                                        <td class="px-3 py-2">
                                            <a href="{{ route('recibos.show', $recibo) }}" class="text-indigo-600 hover:underline">{{ $recibo->numero_completo }}</a>
                                        </td>
                                        <td class="px-3 py-2">{{ $recibo->fecha_emision->format('d/m/Y H:i') }}</td>
                                        <td class="px-3 py-2">{{ $recibo->forma_pago }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($recibo->monto, 2) }}</td>
                                        <td class="px-3 py-2"><x-estado-recibo :recibo="$recibo" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Facturas --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Facturas</h3>
                    @if ($puedeEmitir)
                        <a href="{{ route('facturas.create', $cotizacion) }}"
                           class="px-3 py-1 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Emitir factura</a>
                    @endif
                </div>

                @if ($facturas->isEmpty())
                    <p class="text-gray-500">Esta cotización aún no tiene facturas.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                                <tr>
                                    <th class="px-3 py-2">Factura</th>
                                    <th class="px-3 py-2">Fecha</th>
                                    <th class="px-3 py-2 text-right">Monto</th>
                                    <th class="px-3 py-2">Estado</th>
                                    <th class="px-3 py-2">SIN</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($facturas as $factura)
                                    <tr class="border-t dark:border-gray-700">
                                        <td class="px-3 py-2">
                                            <a href="{{ route('facturas.show', $factura) }}" class="text-indigo-600 hover:underline">{{ $factura->numero_completo }}</a>
                                        </td>
                                        <td class="px-3 py-2">{{ $factura->fecha_emision->format('d/m/Y H:i') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($factura->monto_total, 2) }}</td>
                                        <td class="px-3 py-2"><x-estado-factura :factura="$factura" /></td>
                                        <td class="px-3 py-2">{{ str_replace('_', ' ', $factura->estado_sin) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Acciones --}}
            <div class="flex flex-wrap items-center gap-3">
                @if (in_array($cotizacion->estado, ['APROBADA', 'FACTURADA']))
                    <a href="{{ route('cotizaciones.pdf', $cotizacion) }}" target="_blank"
                       class="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700">Ver PDF</a>
                @endif

                @if ($editable)
                    <a href="{{ route('cotizaciones.edit', $cotizacion) }}"
                       class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Editar</a>
                @endif

                @if ($esAdmin && $cotizacion->estado === 'PENDIENTE')
                    <form method="POST" action="{{ route('cotizaciones.aprobar', $cotizacion) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Aprobar</button>
                    </form>
                @endif

                @if ($esAdmin && $editable)
                    <form method="POST" action="{{ route('cotizaciones.anular', $cotizacion) }}"
                          onsubmit="return confirm('¿Anular esta cotización?')">
                        @csrf @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Anular</button>
                    </form>
                @endif

                <a href="{{ route('cotizaciones.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Volver al listado</a>
            </div>
        </div>
    </div>
</x-app-layout>
