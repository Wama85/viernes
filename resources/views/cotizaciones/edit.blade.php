<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Editar cotización {{ $cotizacion->numero_completo }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                @if (auth()->user()->id_rol != 1)
                    <p class="mb-4 p-3 rounded bg-yellow-100 text-yellow-800 text-sm">
                        Al guardar los cambios, la cotización volverá a quedar pendiente de aprobación.
                    </p>
                @endif

                <form method="POST" action="{{ route('cotizaciones.update', $cotizacion) }}" class="space-y-6" data-validar novalidate>
                    @csrf
                    @method('PUT')
                    @include('cotizaciones._form')

                    <div class="flex items-center gap-3">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Actualizar</button>
                        <a href="{{ route('cotizaciones.show', $cotizacion) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancelar</a>
                    </div>
                </form>

                <x-validacion />
            </div>
        </div>
    </div>
</x-app-layout>
