<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Recibo {{ $recibo->numero_completo }}
            </h2>
            <x-estado-recibo :recibo="$recibo" />
        </div>
    </x-slot>

    @php
        $esAdmin = auth()->user()->id_rol == 1;
        $cotizacion = $recibo->cotizacion;
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

                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Cliente</h3>
                    <p class="font-medium">{{ $cotizacion->cliente->razon_social }}</p>
                    <p>
                        Cotización:
                        <a href="{{ route('cotizaciones.show', $cotizacion) }}" class="text-indigo-600 hover:underline">{{ $cotizacion->numero_completo }}</a>
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <p>Emisión: {{ $recibo->fecha_emision->format('d/m/Y H:i') }}</p>
                    <p>Emitido por: {{ $recibo->emisor->nombre }}</p>
                    <p>Forma de pago: {{ $recibo->forma_pago }}</p>
                    @if ($recibo->referencia_pago)<p>Referencia: {{ $recibo->referencia_pago }}</p>@endif
                    @if ($recibo->aprobador)
                        <p>Aprobado por: {{ $recibo->aprobador->nombre }} ({{ $recibo->fecha_aprobacion->format('d/m/Y H:i') }})</p>
                    @endif
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
                            @foreach ($recibo->detalles as $d)
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
                                <td class="px-3 py-2 text-right">{{ number_format($recibo->monto, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if ($recibo->estado === 'APROBADO')
                    <a href="{{ route('recibos.pdf', $recibo) }}" target="_blank"
                       class="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700">Ver PDF</a>
                @endif

                @if ($esAdmin && $recibo->estado === 'PENDIENTE')
                    <form method="POST" action="{{ route('recibos.aprobar', $recibo) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Aprobar</button>
                    </form>
                @endif

                @if ($esAdmin && $recibo->estado !== 'ANULADO')
                    <a href="{{ route('recibos.edit', $recibo) }}"
                       class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Editar</a>

                    <form method="POST" action="{{ route('recibos.anular', $recibo) }}"
                          @submit.prevent="$dispatch('confirmar-accion', { form: $el, titulo: 'Anular el recibo', texto: 'Vas a anular este recibo. Sus ítems quedarán libres.', boton: 'Anular', peligro: true })">
                        @csrf @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Anular</button>
                    </form>
                @endif

                <a href="{{ route('cotizaciones.show', $cotizacion) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Volver a la cotización</a>
            </div>
        </div>
    </div>
  <x-modal-confirmacion />
</x-app-layout>
