<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Cotizaciones</h2>
            <a href="{{ route('cotizaciones.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                Nueva cotización
            </a>
        </div>
    </x-slot>

    @php $esAdmin = auth()->user()->id_rol == 1; @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 rounded bg-red-100 text-red-800">{{ session('error') }}</div>
            @endif

            <form method="GET" action="{{ route('cotizaciones.index') }}" class="flex flex-wrap gap-3">
                <select name="anio" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    <option value="">Todos los años</option>
                    @foreach ($anios as $anio)
                        <option value="{{ $anio }}" @selected(request('anio') == $anio)>{{ $anio }}</option>
                    @endforeach
                </select>
                <select name="estado" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    <option value="">Todos los estados</option>
                    @foreach (['PENDIENTE', 'APROBADA', 'FACTURADA', 'ANULADA'] as $estado)
                        <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $estado }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-md">Filtrar</button>
                <a href="{{ route('cotizaciones.index') }}" class="px-4 py-2 text-gray-600 dark:text-gray-400">Limpiar</a>
            </form>

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm text-gray-700 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                        <tr>
                            <th class="px-4 py-3">Número</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Emisión</th>
                            <th class="px-4 py-3">Vence</th>
                            <th class="px-4 py-3 text-right">Total (Bs)</th>
                            <th class="px-4 py-3">Estado</th>
                            @if ($esAdmin)<th class="px-4 py-3">Elaborada por</th>@endif
                            <th class="px-4 py-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cotizaciones as $cotizacion)
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-4 py-3 font-medium">{{ $cotizacion->numero_completo }}</td>
                                <td class="px-4 py-3">{{ $cotizacion->cliente->razon_social }}</td>
                                <td class="px-4 py-3">{{ $cotizacion->fecha_emision->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $cotizacion->fecha_vencimiento->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($cotizacion->total, 2) }}</td>
                                <td class="px-4 py-3"><x-estado-cotizacion :cotizacion="$cotizacion" /></td>
                                @if ($esAdmin)<td class="px-4 py-3">{{ $cotizacion->creador->nombre }}</td>@endif
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('cotizaciones.show', $cotizacion) }}" class="text-indigo-600 hover:underline">Ver</a>
                                    @if ($cotizacion->puedeEditarPor(auth()->user()))
                                        <a href="{{ route('cotizaciones.edit', $cotizacion) }}" class="ml-3 text-indigo-600 hover:underline">Editar</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">No hay cotizaciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $cotizaciones->links() }}
        </div>
    </div>
</x-app-layout>
