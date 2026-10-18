<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Usuarios
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 p-4 bg-red-100 text-red-800 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mb-4">
                <a href="{{ route('usuarios.create') }}"
                   class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    + Nuevo Usuario
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($usuarios as $usuario)
                            @php
                                $activo = $usuario->usuario_estado == 1;
                                $titulo = $activo ? 'Dar de baja al usuario' : 'Reactivar al usuario';
                                $texto = $activo
                                    ? 'Vas a dar de baja a ' . $usuario->nombre . '. No podrá iniciar sesión hasta que lo reactives.'
                                    : 'Vas a reactivar a ' . $usuario->nombre . '. Podrá volver a iniciar sesión.';
                            @endphp
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $usuario->nombre }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $usuario->email }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $usuario->rol->nombre_rol }}</td>
                                <td class="px-6 py-4 text-sm">
                                    @if ($activo)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Activo</span>
                                    @else
                                        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Dado de baja</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-right space-x-2">
                                    <a href="{{ route('usuarios.edit', $usuario) }}"
                                       class="text-indigo-600 hover:underline">Editar</a>

                                    <form action="{{ route('usuarios.cambiarEstado', $usuario) }}"
                                          method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button"
                                                class="{{ $activo ? 'text-red-600' : 'text-green-600' }} hover:underline"
                                                @click="$dispatch('confirmar-accion', {
                                                    form: $el.closest('form'),
                                                    titulo: @js($titulo),
                                                    texto: @js($texto),
                                                    boton: @js($activo ? 'Dar de baja' : 'Reactivar'),
                                                    peligro: {{ $activo ? 'true' : 'false' }}
                                                })">
                                            {{ $activo ? 'Dar de baja' : 'Reactivar' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal-confirmacion />
</x-app-layout>
