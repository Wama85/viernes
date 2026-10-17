<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Papelera
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 rounded bg-red-100 text-red-800">{{ session('error') }}</div>
            @endif

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Aquí están los registros dados de baja. Puedes restaurarlos o eliminarlos definitivamente.
                Solo se pueden eliminar los que nunca se usaron en cotizaciones, recibos, facturas o stock.
            </p>

            {{-- Pestañas --}}
            <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
                @foreach ($tipos as $clave => $info)
                    <a href="{{ route('papelera.index', ['tipo' => $clave]) }}"
                       class="-mb-px border-b-2 px-4 py-2 text-sm font-medium
                              {{ $clave === $tipo
                                    ? 'border-indigo-600 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        {{ $info['titulo'] }}
                        <span class="ml-1 rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                            {{ $conteos[$clave] }}
                        </span>
                    </a>
                @endforeach
            </div>

            @unless ($verificable)
                <div class="p-3 rounded bg-yellow-100 text-yellow-800 text-sm">
                    No se puede comprobar si estos registros se usan en otras tablas (faltan claves foráneas en la base de datos),
                    así que la eliminación definitiva está bloqueada. Puedes restaurarlos sin problema.
                </div>
            @endunless

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm text-gray-700 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                        <tr>
                            <th class="px-4 py-3">Registro</th>
                            <th class="px-4 py-3">Uso</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registros as $registro)
                            @php
                                if ($tipo === 'clientes') {
                                    $nombre = $registro->razon_social;
                                    $detalle = $registro->documento_identidad
                                        . ($registro->complemento ? '-' . $registro->complemento : '')
                                        . ' · ' . ($registro->tipoDocumento->nombre_tipo_documento ?? '');
                                } elseif ($tipo === 'productos') {
                                    $nombre = $registro->descripcion_producto;
                                    $detalle = 'Categoría: ' . ($registro->categoria->nombre_categoria ?? '—');
                                } else {
                                    $nombre = $registro->nombre_categoria;
                                    $detalle = null;
                                }

                                $enUso = $usos[(int) $registro->getKey()] ?? null;
                                $puedeEliminar = $verificable && ! $enUso;
                                $aviso = '¿Eliminar definitivamente «' . $nombre . '»? Esta acción no se puede deshacer.';
                            @endphp
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $nombre }}</div>
                                    @if ($detalle)
                                        <div class="text-xs text-gray-500">{{ $detalle }}</div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-xs">
                                    @if ($enUso)
                                        <span class="rounded bg-amber-100 px-2 py-0.5 text-amber-800">En uso: {{ $enUso }}</span>
                                    @else
                                        <span class="text-gray-500">Sin uso</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('papelera.restaurar', [$tipo, $registro->getKey()]) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-green-600 hover:underline">Restaurar</button>
                                    </form>

                                    @if ($puedeEliminar)
                                        <form method="POST" action="{{ route('papelera.eliminar', [$tipo, $registro->getKey()]) }}"
                                              class="inline ml-3" onsubmit="return confirm(@js($aviso));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Eliminar definitivamente</button>
                                        </form>
                                    @else
                                        <span class="ml-3 text-xs text-gray-400">No se puede eliminar</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                    La papelera de {{ mb_strtolower($tipos[$tipo]['titulo']) }} está vacía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
