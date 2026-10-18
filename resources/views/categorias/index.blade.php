<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Categorías
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-4">
                <a href="{{ route('categorias.create') }}"
                   class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    + Nueva Categoría
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($categorias as $categoria)
                            @php
                                $activa = $categoria->categoria_estado == 1;
                                $titulo = $activa ? 'Dar de baja la categoría' : 'Reactivar la categoría';
                                $texto = $activa
                                    ? 'Vas a dar de baja la categoría «' . $categoria->nombre_categoria . '». Podrás reactivarla cuando quieras.'
                                    : 'Vas a reactivar la categoría «' . $categoria->nombre_categoria . '».';
                            @endphp
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $categoria->nombre_categoria }}</td>
                                <td class="px-6 py-4 text-sm">
                                    @if ($activa)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Activa</span>
                                    @else
                                        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Dada de baja</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-right space-x-2">
                                    <a href="{{ route('categorias.edit', $categoria) }}"
                                       class="text-indigo-600 hover:underline">Editar</a>

                                    <form action="{{ route('categorias.cambiarEstado', $categoria) }}"
                                          method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button"
                                                class="{{ $activa ? 'text-red-600' : 'text-green-600' }} hover:underline"
                                                @click="$dispatch('confirmar-accion', {
                                                    form: $el.closest('form'),
                                                    titulo: @js($titulo),
                                                    texto: @js($texto),
                                                    boton: @js($activa ? 'Dar de baja' : 'Reactivar'),
                                                    peligro: {{ $activa ? 'true' : 'false' }}
                                                })">
                                            {{ $activa ? 'Dar de baja' : 'Reactivar' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">
                                    Todavía no hay categorías registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal-confirmacion />
</x-app-layout>
