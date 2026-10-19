<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Clientes
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-4">
                <a href="{{ route('clientes.create') }}"
                   class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    + Nuevo Cliente
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Documento</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Razón social</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contacto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($clientes as $cliente)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $cliente->documento_identidad }}{{ $cliente->complemento ? '-' . $cliente->complemento : '' }}
                                    <div class="text-xs text-gray-500">{{ $cliente->tipoDocumento->nombre_tipo_documento }}</div>
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $cliente->razon_social }}
                                    @if ($cliente->nombre_responsable)
                                        <div class="text-xs text-gray-500">Resp.: {{ $cliente->nombre_responsable }}</div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                    @foreach ($cliente->correos as $correo)
                                        <div>{{ $correo->correo }}</div>
                                    @endforeach
                                    @foreach ($cliente->telefonos as $telefono)
                                        <div class="text-xs text-gray-500">{{ $telefono->telefono }}</div>
                                    @endforeach
                                    @if ($cliente->correos->isEmpty() && $cliente->telefonos->isEmpty())
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-sm">
                                    @if ($cliente->cliente_estado == 1)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Activo</span>
                                    @else
                                        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Dado de baja</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-sm text-right space-x-2">
                                    <a href="{{ route('clientes.edit', $cliente) }}"
                                       class="text-indigo-600 hover:underline">Editar</a>

                                    <form action="{{ route('clientes.cambiarEstado', $cliente) }}"
                                          method="POST" class="inline"
                                          @submit.prevent="$dispatch('confirmar-accion', { form: $el, titulo: 'Cambiar estado', texto: '¿Confirmas este cambio de estado del cliente?', boton: 'Confirmar', peligro: false })">
                                        @csrf
                                        @method('PATCH')
                                        @if ($cliente->cliente_estado == 1)
                                            <button type="submit" class="text-red-600 hover:underline">Dar de baja</button>
                                        @else
                                            <button type="submit" class="text-green-600 hover:underline">Reactivar</button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                    Todavía no hay clientes registrados.
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
