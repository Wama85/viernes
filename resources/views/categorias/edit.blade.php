<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Editar Categoría
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 p-6 shadow-sm sm:rounded-lg">

                <form method="POST" action="{{ route('categorias.update', $categoria) }}">
                    @csrf
                    @method('PUT')

                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Los campos marcados con <span class="text-red-600">*</span> son obligatorios.
                    </p>

                    <div class="mb-4">
                        <x-input-label for="nombre_categoria">
                            Nombre de la categoría <span class="text-red-600">*</span>
                        </x-input-label>
                        <x-text-input id="nombre_categoria" name="nombre_categoria" type="text"
                                      class="mt-1 block w-full uppercase"
                                      value="{{ old('nombre_categoria', $categoria->nombre_categoria) }}" required autofocus />
                        <x-input-error :messages="$errors->get('nombre_categoria')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-6 space-x-3">
                        <a href="{{ route('categorias.index') }}" class="text-gray-600 dark:text-gray-400">Cancelar</a>
                        <x-primary-button>Actualizar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
