<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Productos y servicios</h2>
            @if (auth()->user()->id_rol == 1)
                <a href="{{ route('productos.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    Nuevo producto
                </a>
            @endif
        </div>
    </x-slot>

    @php
        $esAdmin = auth()->user()->id_rol == 1;
        $numero = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif

            {{-- Filtros --}}
            <form method="GET" action="{{ route('productos.index') }}" class="flex flex-wrap gap-3">
                <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por descripción"
                       class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                <select name="categoria" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    <option value="">Todas las categorías</option>
                    @foreach ($categorias as $c)
                        <option value="{{ $c->id_categoria }}" @selected(request('categoria') == $c->id_categoria)>
                            {{ $c->nombre_categoria }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-md">Filtrar</button>
                <a href="{{ route('productos.index') }}" class="px-4 py-2 text-gray-600 dark:text-gray-400">Limpiar</a>
            </form>

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm text-gray-700 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                        <tr>
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3">Categoría</th>
                            <th class="px-4 py-3">Unidad</th>
                            @if ($esAdmin)<th class="px-4 py-3 text-right">Costo</th>@endif
                            <th class="px-4 py-3 text-right">Precio</th>
                            <th class="px-4 py-3 text-right">Stock</th>
                            <th class="px-4 py-3">Estado</th>
                            @if ($esAdmin)<th class="px-4 py-3">Acciones</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($productos as $producto)
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-4 py-3">{{ $producto->id_producto }}</td>
                                <td class="px-4 py-3">{{ $producto->descripcion_producto }}</td>
                                <td class="px-4 py-3">{{ $producto->categoria->nombre_categoria }}</td>
                                <td class="px-4 py-3">{{ $producto->unidadMedida->nombre_unidad_medida }}</td>
                                @if ($esAdmin)
                                    <td class="px-4 py-3 text-right">{{ number_format($producto->costo_compra, 2) }}</td>
                                @endif
                                <td class="px-4 py-3 text-right">{{ number_format($producto->precio_venta, 2) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if (! $producto->controla_stock)
                                        —
                                    @elseif ($producto->estaAgotado())
                                        <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Agotado</span>
                                    @elseif ($producto->stockBajo())
                                        <span class="rounded bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800">{{ $numero($producto->stock) }} · Bajo</span>
                                    @else
                                        {{ $numero($producto->stock) }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($producto->producto_estado)
                                        <span class="text-green-600">Activo</span>
                                    @else
                                        <span class="text-red-600">De baja</span>
                                    @endif
                                </td>
                                @if ($esAdmin)
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ route('productos.edit', $producto) }}" class="text-indigo-600 hover:underline">Editar</a>
                                        @if ($producto->controla_stock)
                                            <a href="{{ route('productos.movimientos', $producto) }}" class="ml-3 text-indigo-600 hover:underline">Stock</a>
                                        @endif
                                        <form method="POST" action="{{ route('productos.cambiarEstado', $producto) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="ml-3 text-red-600 hover:underline">
                                                {{ $producto->producto_estado ? 'Dar de baja' : 'Reactivar' }}
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">No hay productos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $productos->links() }}
        </div>
    </div>
</x-app-layout>
