<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Facturas</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                    <select name="estado" class="rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                        <option value="">Todos</option>
                        @foreach (['PENDIENTE', 'APROBADA', 'ANULADA'] as $e)
                            <option value="{{ $e }}" @selected($estado === $e)>{{ $e }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Filtrar</button>
            </form>

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm text-gray-700 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                        <tr>
                            <th class="px-3 py-2">Factura</th>
                            <th class="px-3 py-2">Cotización</th>
                            <th class="px-3 py-2">Cliente</th>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2 text-right">Monto</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">SIN</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($facturas as $f)
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-3 py-2">
                                    <a href="{{ route('facturas.show', $f) }}" class="text-indigo-600 hover:underline">{{ $f->numero_completo }}</a>
                                </td>
                                <td class="px-3 py-2">{{ $f->cotizacion->numero_completo }}</td>
                                <td class="px-3 py-2">{{ $f->cliente_razon_social }}</td>
                                <td class="px-3 py-2">{{ $f->fecha_emision->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($f->monto_total, 2) }}</td>
                                <td class="px-3 py-2"><x-estado-factura :factura="$f" /></td>
                                <td class="px-3 py-2">{{ str_replace('_', ' ', $f->estado_sin) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No hay facturas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $facturas->links() }}
        </div>
    </div>
</x-app-layout>
