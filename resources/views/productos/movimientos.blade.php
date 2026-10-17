<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Movimientos de stock</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $producto->descripcion_producto }}</p>
            </div>
            <a href="{{ route('productos.index') }}" class="text-sm text-gray-600 hover:underline dark:text-gray-400">Volver a productos</a>
        </div>
    </x-slot>

    @php
        $numero = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
        $campo = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm';
        $colores = [
            'ENTRADA'    => 'bg-green-100 text-green-800',
            'SALIDA'     => 'bg-red-100 text-red-800',
            'DEVOLUCION' => 'bg-blue-100 text-blue-800',
            'AJUSTE'     => 'bg-yellow-100 text-yellow-800',
        ];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="rounded bg-green-100 p-3 text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded bg-red-100 p-3 text-red-800">{{ session('error') }}</div>
            @endif

            {{-- Resumen --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Stock actual</p>
                    <p class="mt-1 text-3xl font-bold {{ $producto->estaAgotado() ? 'text-red-600' : 'text-[#0c3082] dark:text-blue-300' }}">
                        {{ $numero($producto->stock) }}
                    </p>
                    @if ($producto->estaAgotado())
                        <span class="mt-1 inline-block rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Agotado</span>
                    @elseif ($producto->stockBajo())
                        <span class="mt-1 inline-block rounded bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800">Stock bajo</span>
                    @endif
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Stock mínimo</p>
                    <p class="mt-1 text-3xl font-bold text-gray-700 dark:text-gray-200">{{ $numero($producto->stock_minimo) }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unidad</p>
                    <p class="mt-1 text-xl font-semibold text-gray-700 dark:text-gray-200">{{ $producto->unidadMedida->nombre_unidad_medida }}</p>
                </div>
            </div>

            {{-- Registrar movimiento --}}
            <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                <h3 class="mb-3 font-semibold text-gray-800 dark:text-gray-100">Registrar movimiento</h3>

                @if ($errors->any())
                    <div class="mb-3 rounded bg-red-100 p-3 text-sm text-red-800">
                        <ul class="ml-5 list-disc">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                @if ($producto->controla_stock)
                    <form method="POST" action="{{ route('productos.movimientos.store', $producto) }}"
                          x-data="{ tipo: '{{ old('tipo', 'ENTRADA') }}' }"
                          class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo <span class="text-red-600">*</span></label>
                            <select name="tipo" x-model="tipo" class="{{ $campo }}">
                                <option value="ENTRADA">Ingreso de mercadería</option>
                                <option value="AJUSTE">Ajuste por conteo</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span x-text="tipo === 'ENTRADA' ? 'Cantidad que ingresa' : 'Stock real contado'"></span>
                                <span class="text-red-600">*</span>
                            </label>
                            <input type="number" name="cantidad" step="0.01" min="0" required
                                   value="{{ old('cantidad') }}" class="{{ $campo }}">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Motivo <span x-show="tipo === 'AJUSTE'" class="text-red-600">*</span>
                            </label>
                            <input type="text" name="motivo" maxlength="255" value="{{ old('motivo') }}"
                                   class="{{ $campo }} uppercase"
                                   :placeholder="tipo === 'ENTRADA' ? 'Ej.: compra a proveedor' : 'Ej.: conteo físico, producto dañado'">
                        </div>

                        <div class="md:col-span-4">
                            <button type="submit" class="rounded-md bg-[#0c3082] px-4 py-2 font-semibold text-white hover:bg-[#0a2668]">
                                Registrar
                            </button>
                            <p class="mt-2 text-xs text-gray-500" x-show="tipo === 'AJUSTE'">
                                El ajuste fija el stock al valor que indiques; el sistema calcula la diferencia.
                            </p>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-gray-500">Este producto no controla stock. Actívalo desde "Editar" si lo necesitas.</p>
                @endif
            </div>

            {{-- Historial --}}
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm dark:bg-gray-800">
                <table class="min-w-full text-sm text-gray-700 dark:text-gray-300">
                    <thead class="bg-gray-50 text-left dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2 text-right">Cantidad</th>
                            <th class="px-3 py-2 text-right">Stock</th>
                            <th class="px-3 py-2">Motivo / documento</th>
                            <th class="px-3 py-2">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimientos as $m)
                            <tr class="border-t dark:border-gray-700">
                                <td class="whitespace-nowrap px-3 py-2">{{ $m->fecha_hora->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $colores[$m->tipo] ?? 'bg-gray-100 text-gray-700' }}">{{ $m->tipo }}</span>
                                </td>
                                <td class="px-3 py-2 text-right font-semibold {{ $m->cantidad < 0 ? 'text-red-600' : 'text-green-700 dark:text-green-400' }}">
                                    {{ $m->cantidad > 0 ? '+' : '' }}{{ $numero($m->cantidad) }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">{{ $numero($m->stock_anterior) }} → {{ $numero($m->stock_posterior) }}</td>
                                <td class="px-3 py-2">
                                    {{ $m->motivo }}
                                    @if ($m->tipo_documento === 'RECIBO' && $m->id_documento)
                                        · <a href="{{ route('recibos.show', $m->id_documento) }}" class="text-indigo-600 hover:underline">ver recibo</a>
                                    @elseif ($m->tipo_documento === 'FACTURA' && $m->id_documento)
                                        · <a href="{{ route('facturas.show', $m->id_documento) }}" class="text-indigo-600 hover:underline">ver factura</a>
                                    @endif
                                    @if ($m->revertido)
                                        <span class="text-xs text-gray-400">(devuelto)</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $m->usuario->nombre ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">Aún no hay movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $movimientos->links() }}
        </div>
    </div>
</x-app-layout>
