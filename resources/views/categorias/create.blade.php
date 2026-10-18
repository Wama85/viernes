<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Nueva Categoría
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 p-6 shadow-sm sm:rounded-lg">

                <form method="POST" action="{{ route('categorias.store') }}" data-validar novalidate>
                    @csrf

                    <div data-resumen class="val-resumen" hidden></div>

                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Los campos marcados con <span class="text-red-600">*</span> son obligatorios.
                    </p>

                    <x-campo-validado name="nombre_categoria" label="Nombre de la categoría"
                                      :obligatorio="true" :mayusculas="true" :maxlength="100" :contador="true"
                                      reglas="requerido|min:2|razon|max:100" autofocus />

                    <div class="flex items-center justify-end mt-6 space-x-3">
                        <a href="{{ route('categorias.index') }}" class="text-gray-600 dark:text-gray-400">Cancelar</a>
                        <x-primary-button>Guardar</x-primary-button>
                    </div>
                </form>

                <x-validacion />
            </div>
        </div>
    </div>
</x-app-layout>
