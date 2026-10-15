<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Emitir recibo — {{ $cotizacion->numero_completo }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 text-sm text-gray-700 dark:text-gray-300">
                <p>Cliente: <span class="font-semibold">{{ $cotizacion->cliente->razon_social }}</span></p>
                <p class="text-xs text-gray-500 mt-1">
                    Elige los ítems que el cliente aprobó. Los ítems que ya tienen recibo no aparecen.
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                @include('recibos._form', [
                    'action'   => route('recibos.store', $cotizacion),
                    'boton'    => 'Emitir recibo',
                    'cancelar' => route('cotizaciones.show', $cotizacion),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
