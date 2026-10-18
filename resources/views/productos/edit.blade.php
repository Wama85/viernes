<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Editar producto #{{ $producto->id_producto }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('productos.update', $producto) }}" enctype="multipart/form-data"
                      class="space-y-6" data-validar novalidate>
                    @csrf
                    @method('PUT')
                    @include('productos._form')

                    <div class="flex items-center gap-3">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Actualizar</button>
                        <a href="{{ route('productos.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancelar</a>
                    </div>
                </form>

                <x-validacion />
            </div>
        </div>
    </div>
</x-app-layout>
