<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Panel</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Hola, {{ auth()->user()->nombre }}</p>
            </div>

            <div class="flex gap-2">
                @if (Route::has('cotizaciones.create'))
                    <a href="{{ route('cotizaciones.create') }}"
                       class="rounded-md bg-[#0c3082] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0a2668]">
                        + Nueva cotización
                    </a>
                @endif
                @if (Route::has('clientes.create'))
                    <a href="{{ route('clientes.create') }}"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        + Nuevo cliente
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            {{-- Indicadores --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Cotizaciones del mes</p>
                    <p class="mt-2 text-3xl font-bold text-[#0c3082] dark:text-blue-300">{{ $tarjetas['del_mes'] }}</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Por enviar al cliente</p>
                    <p class="mt-2 text-3xl font-bold text-[#0c3082] dark:text-blue-300">{{ $tarjetas['por_enviar'] }}</p>
                    <p class="mt-1 text-xs text-gray-400">Aprobadas y vigentes</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Vencen en 24 horas</p>
                    <p class="mt-2 text-3xl font-bold {{ $tarjetas['por_vencer'] > 0 ? 'text-red-600' : 'text-[#0c3082] dark:text-blue-300' }}">{{ $tarjetas['por_vencer'] }}</p>
                    <p class="mt-1 text-xs text-gray-400">Cotizaciones aprobadas</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Recibos aprobados del mes</p>
                    <p class="mt-2 text-3xl font-bold text-[#0c3082] dark:text-blue-300">Bs {{ number_format($tarjetas['ingresos_mes'], 2) }}</p>
                </div>
            </div>

            {{-- Pendientes de aprobación (solo administrador) --}}
            @if ($esAdmin)
                <div>
                    <h3 class="mb-3 text-lg font-semibold text-gray-800 dark:text-gray-200">Pendientes de aprobación</h3>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

                        {{-- Cotizaciones --}}
                        <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="font-semibold text-gray-800 dark:text-gray-100">Cotizaciones</h4>
                                <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800">{{ $pendientes['cotizaciones']['total'] }}</span>
                            </div>
                            <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                                @forelse ($pendientes['cotizaciones']['lista'] as $c)
                                    <li class="py-2">
                                        <a href="{{ route('cotizaciones.show', $c) }}" class="font-medium text-[#0c3082] hover:underline dark:text-blue-300">{{ $c->numero_completo }}</a>
                                        <span class="text-gray-500 dark:text-gray-400">· {{ $c->cliente->razon_social }}</span>
                                        <div class="text-xs text-gray-400">Bs {{ number_format($c->total, 2) }} · {{ $c->fecha_emision->format('d/m H:i') }}</div>
                                    </li>
                                @empty
                                    <li class="py-2 text-gray-400">Sin pendientes.</li>
                                @endforelse
                            </ul>
                        </div>

                        {{-- Recibos --}}
                        <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="font-semibold text-gray-800 dark:text-gray-100">Recibos</h4>
                                <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800">{{ $pendientes['recibos']['total'] }}</span>
                            </div>
                            <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                                @forelse ($pendientes['recibos']['lista'] as $r)
                                    <li class="py-2">
                                        <a href="{{ route('recibos.show', $r) }}" class="font-medium text-[#0c3082] hover:underline dark:text-blue-300">{{ $r->numero_completo }}</a>
                                        <span class="text-gray-500 dark:text-gray-400">· {{ $r->cotizacion->cliente->razon_social }}</span>
                                        <div class="text-xs text-gray-400">Bs {{ number_format($r->monto, 2) }} · {{ $r->fecha_emision->format('d/m H:i') }}</div>
                                    </li>
                                @empty
                                    <li class="py-2 text-gray-400">Sin pendientes.</li>
                                @endforelse
                            </ul>
                        </div>

                        {{-- Facturas --}}
                        <div class="rounded-lg bg-white p-5 shadow-sm dark:bg-gray-800">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="font-semibold text-gray-800 dark:text-gray-100">Facturas</h4>
                                <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800">{{ $pendientes['facturas']['total'] }}</span>
                            </div>
                            <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                                @forelse ($pendientes['facturas']['lista'] as $f)
                                    <li class="py-2">
                                        <a href="{{ route('facturas.show', $f) }}" class="font-medium text-[#0c3082] hover:underline dark:text-blue-300">Factura #{{ $f->id_factura }}</a>
                                        <span class="text-gray-500 dark:text-gray-400">· {{ $f->cliente_razon_social }}</span>
                                        <div class="text-xs text-gray-400">Bs {{ number_format($f->monto_total, 2) }} · {{ $f->fecha_emision->format('d/m H:i') }}</div>
                                    </li>
                                @empty
                                    <li class="py-2 text-gray-400">Sin pendientes.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
