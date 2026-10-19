<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Factura {{ $factura->numero_completo }}
            </h2>
            <x-estado-factura :factura="$factura" />
        </div>
    </x-slot>

    @php
        $esAdmin = auth()->user()->id_rol == 1;
        $cotizacion = $factura->cotizacion;
    @endphp

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 rounded bg-red-100 text-red-800">{{ session('error') }}</div>
            @endif

            @if ($factura->estado_sin !== 'VALIDADA')
                <div class="p-3 rounded bg-yellow-50 text-yellow-800 text-sm border border-yellow-200">
                    Documento interno: todavía no fue validado por el SIN, por lo tanto aún no es una factura válida.
                </div>
            @endif

            @if ($factura->estado_sin === 'VALIDADA' && $factura->esDePrueba())
                <div class="p-3 rounded bg-blue-50 text-blue-800 text-sm border border-blue-200">
                    Factura validada en el ambiente de pruebas del SIN: <strong>no tiene valor legal</strong>.
                </div>
            @endif

            @if ($factura->mensaje_sin && $factura->estado_sin !== 'VALIDADA')
                <div class="p-3 rounded bg-red-50 text-red-800 text-sm border border-red-200">
                    Respuesta del SIN: {{ $factura->mensaje_sin }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-6 text-sm text-gray-700 dark:text-gray-300">

                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Cliente (datos al emitir)</h3>
                    <p class="font-medium">{{ $factura->cliente_razon_social }}</p>
                    <p>NIT/CI: {{ $factura->cliente_documento_completo }}</p>
                    @if ($factura->cliente_correo)<p>Correo: {{ $factura->cliente_correo }}</p>@endif
                    <p>
                        Cotización:
                        <a href="{{ route('cotizaciones.show', $cotizacion) }}" class="text-indigo-600 hover:underline">{{ $cotizacion->numero_completo }}</a>
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <p>Emisión: {{ $factura->fecha_emision->format('d/m/Y H:i') }}</p>
                    <p>Emitida por: {{ $factura->emisor->nombre }}</p>
                    <p>Forma de pago: {{ $factura->forma_pago }}</p>
                    <p>Estado en el SIN: <span class="font-semibold">{{ str_replace('_', ' ', $factura->estado_sin) }}</span></p>
                    @if ($factura->aprobador)
                        <p>Aprobada por: {{ $factura->aprobador->nombre }} ({{ $factura->fecha_aprobacion->format('d/m/Y H:i') }})</p>
                    @endif
                    @if ($factura->cuf)<p class="sm:col-span-2 break-all">CUF: {{ $factura->cuf }}</p>@endif
                    @if ($factura->codigo_estado)<p>Código de respuesta del SIN: {{ $factura->codigo_estado }}</p>@endif
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                            <tr>
                                <th class="px-3 py-2">Descripción</th>
                                <th class="px-3 py-2 text-right">Cant.</th>
                                <th class="px-3 py-2 text-right">Precio</th>
                                <th class="px-3 py-2 text-right">Desc.</th>
                                <th class="px-3 py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($factura->detalles as $d)
                                <tr class="border-t dark:border-gray-700">
                                    <td class="px-3 py-2">
                                        {{ $d->descripcion }}
                                        @if ($d->descripcion_adicional)
                                            <div class="text-xs text-gray-500">{{ $d->descripcion_adicional }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format($d->cantidad, 2), '0'), '.') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($d->precio_unitario, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($d->descuento, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($d->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t dark:border-gray-700 font-bold">
                                <td colspan="4" class="px-3 py-2 text-right">TOTAL (Bs)</td>
                                <td class="px-3 py-2 text-right">{{ number_format($factura->monto_total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if ($esAdmin && $factura->estado === 'PENDIENTE')
                    <form method="POST" action="{{ route('facturas.aprobar', $factura) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Aprobar</button>
                    </form>
                @endif

                @if ($esAdmin && $factura->estado === 'APROBADA' && in_array($factura->estado_sin, ['NO_ENVIADA', 'RECHAZADA']))
                    <form method="POST" action="{{ route('facturas.enviar-sin', $factura) }}"
                          x-data="{ enviando: false }" @submit="enviando = true">
                        @csrf @method('PATCH')
                        <button type="submit" :disabled="enviando"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-60">
                            <span x-show="!enviando">{{ $factura->estado_sin === 'RECHAZADA' ? 'Reintentar envío al SIN' : 'Enviar al SIN' }}</span>
                            <span x-show="enviando" x-cloak>Enviando…</span>
                        </button>
                    </form>
                @endif

               @if ($factura->numero)
                    <a href="{{ route('facturas.pdf', $factura) }}" target="_blank"
                       class="px-4 py-2 rounded-md"
                       style="background-color:#4b5563; color:#ffffff; display:inline-block;">Ver PDF</a>
                    <a href="{{ route('facturas.pdf', ['factura' => $factura, 'descargar' => 1]) }}"
                       class="px-4 py-2 rounded-md"
                       style="background-color:#0284c7; color:#ffffff; display:inline-block;">Descargar PDF</a>
                @endif

                @if ($esAdmin && $factura->estado !== 'ANULADA')
                    <form method="POST" action="{{ route('facturas.anular', $factura) }}"
                          @submit.prevent="$dispatch('confirmar-accion', { form: $el, titulo: 'Anular la factura', texto: 'Vas a anular esta factura. Sus ítems quedarán libres.', boton: 'Anular', peligro: true })">
                        @csrf @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Anular</button>
                    </form>
                @endif

                <a href="{{ route('cotizaciones.show', $cotizacion) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Volver a la cotización</a>
            </div>

            {{-- Tras pulsar «Enviar al SIN» sin autorización, se muestra el documento sin valor legal --}}
            @if ($factura->numero && session('mostrar_pdf'))
                <iframe src="{{ route('facturas.pdf', $factura) }}"
                        class="h-[850px] w-full rounded-lg border border-gray-300 dark:border-gray-600"></iframe>
            @endif
        </div>
    </div>
    <x-modal-confirmacion />
</x-app-layout>
