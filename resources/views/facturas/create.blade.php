<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Emitir factura — {{ $cotizacion->numero_completo }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 text-sm text-gray-700 dark:text-gray-300 space-y-1">
                <p>Cliente: <span class="font-semibold">{{ $cotizacion->cliente->razon_social }}</span></p>
                <p>NIT/CI: {{ $cotizacion->cliente->documento_identidad }}{{ $cotizacion->cliente->complemento ? '-' . $cotizacion->cliente->complemento : '' }}</p>
                <p class="text-xs text-gray-500">
                    Los datos del cliente quedan fijos en la factura. Revísalos antes de emitir: si hay un error, corrígelo primero en la ficha del cliente.
                </p>
            </div>

            @php
                $descLineas = round((float) $items->sum('descuento'), 2);
                $descGeneral = round((float) $cotizacion->descuento_general, 2);
            @endphp
            @if ($descLineas > 0 || $descGeneral > 0)
                <div class="p-3 rounded text-sm" style="background-color:#eff6ff; color:#1e3a8a; border:1px solid #bfdbfe;">
                    <p class="font-semibold">Esta cotización ya tiene descuento: recuérdaselo al cliente.</p>
                    <ul class="mt-1 ml-5 list-disc">
                        @if ($descLineas > 0)
                            <li>Descuento en los ítems: Bs {{ number_format($descLineas, 2) }} (ya está en los precios de abajo).</li>
                        @endif
                        @if ($descGeneral > 0)
                            <li>
                                Descuento general: Bs {{ number_format($descGeneral, 2) }}. Se aplica a la factura repartido entre los ítems
                                que factures, para que coincida con la cotización.
                            </li>
                        @endif
                    </ul>
                    <p class="mt-1 text-xs">Como la cotización ya tiene descuento, no se puede dar otro en la factura: deja vacío «Descuento adicional».</p>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                @include('facturas._form', [
                    'action'   => route('facturas.store', $cotizacion),
                    'boton'    => 'Emitir factura',
                    'cancelar' => route('cotizaciones.show', $cotizacion),
                    'nitEstado' => $nitEstado ?? null,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
