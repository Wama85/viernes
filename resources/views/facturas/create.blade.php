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

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                @include('facturas._form', [
                    'action'   => route('facturas.store', $cotizacion),
                    'boton'    => 'Emitir factura',
                    'cancelar' => route('cotizaciones.show', $cotizacion),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
