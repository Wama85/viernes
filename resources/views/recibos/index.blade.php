<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Recibos</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 mb-1">Año</label>
                    <select name="anio" class="rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                        <option value="">Todos</option>
                        @foreach ($anios as $a)
                            <option value="{{ $a }}" @selected((string) $anio === (string) $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                    <select name="estado" class="rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                        <option value="">Todos</option>
                        @foreach (['PENDIENTE', 'APROBADO', 'ANULADO'] as $e)
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
                            <th class="px-3 py-2">Recibo</th>
                            <th class="px-3 py-2">Cotización</th>
                            <th class="px-3 py-2">Cliente</th>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Forma de pago</th>
                            <th class="px-3 py-2 text-right">Monto</th>
                            <th class="px-3 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recibos as $r)
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-3 py-2">
                                    <a href="{{ route('recibos.show', $r) }}" class="text-indigo-600 hover:underline">{{ $r->numero_completo }}</a>
                                </td>
                                <td class="px-3 py-2">{{ $r->cotizacion->numero_completo }}</td>
                                <td class="px-3 py-2">{{ $r->cotizacion->cliente->razon_social }}</td>
                                <td class="px-3 py-2">{{ $r->fecha_emision->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">{{ $r->forma_pago }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($r->monto, 2) }}</td>
                                <td class="px-3 py-2"><x-estado-recibo :recibo="$r" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No hay recibos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $recibos->links() }}
        </div>
    </div>
</x-app-layout>
