<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Consultas</h2>
    </x-slot>

    @php
        $pestanas = ['cotizaciones' => 'Cotizaciones', 'recibos' => 'Recibos', 'facturas' => 'Facturas'];
        $hayFiltros = collect($f)->filter(fn ($v) => filled($v))->isNotEmpty();
        $entrada = 'rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 w-full';
    @endphp

    <div class="py-8">
            <x-validacion />
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Pestañas --}}
            <div style="display:flex; gap:6px; border-bottom:1px solid #d1d5db;">
                @foreach ($pestanas as $clave => $nombre)
                    <a href="{{ route('consultas.index', ['tipo' => $clave]) }}"
                       style="padding:8px 18px; font-weight:600; font-size:14px; text-decoration:none; border-radius:6px 6px 0 0;
                              {{ $tipo === $clave ? 'background-color:#0c3082; color:#ffffff;' : 'background-color:#e5e7eb; color:#374151;' }}">
                        {{ $nombre }}
                    </a>
                @endforeach
            </div>

            @if ($errors->any())
                <div class="p-3 rounded bg-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Filtros --}}
            <form method="GET" action="{{ route('consultas.index') }}" data-validar novalidate
                  class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 text-sm">
                <input type="hidden" name="tipo" value="{{ $tipo }}">

                {{-- Errores del servidor (ej. si alguien escribe la dirección a mano) --}}
                <div data-resumen class="val-resumen" @if (! $errors->any()) hidden @endif>
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <x-campo-validado name="q" label="Institución / cliente" :value="$f['q']"
                            reglas="min:2|max:100" maxlength="100" placeholder="Ej: colegio san, alcaldía…" />
                    </div>
                    <div>
                        <x-campo-validado name="documento" label="NIT / CI" :value="$f['documento']"
                            reglas="min:3|max:30" maxlength="30" mayusculas />
                    </div>
                    <div>
                        <x-campo-validado name="numero" label="Número" :value="$f['numero']"
                            reglas="max:30" maxlength="30" mayusculas placeholder="Ej: COT-2026-0007 o 7" />
                    </div>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                        <select name="estado" class="{{ $entrada }}">
                            <option value="">Todos</option>
                            @foreach ($estados as $e)
                                <option value="{{ $e }}" @selected($f['estado'] === $e)>{{ $e }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-1">Emitida desde</label>
                        <input type="date" name="desde" id="f-desde" value="{{ $f['desde'] }}" max="{{ now()->toDateString() }}" class="{{ $entrada }}">
                    </div>
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-1">Emitida hasta</label>
                        <input type="date" name="hasta" id="f-hasta" value="{{ $f['hasta'] }}" max="{{ now()->toDateString() }}" class="{{ $entrada }}">
                    </div>
                    @if ($esAdmin)
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 mb-1">Vendedor</label>
                            <select name="vendedor" class="{{ $entrada }}">
                                <option value="">Todos</option>
                                @foreach ($vendedores as $v)
                                    <option value="{{ $v->id_usuario }}" @selected((string) $f['vendedor'] === (string) $v->id_usuario)>{{ $v->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" style="background-color:#0c3082; color:#ffffff;" class="px-5 py-2 rounded-md font-semibold">Buscar</button>
                    <a href="{{ route('consultas.index', ['tipo' => $tipo]) }}" class="text-gray-600 dark:text-gray-400 hover:underline">Limpiar</a>
                    <span class="ml-auto text-gray-500 dark:text-gray-400">
                        {{ $resultados->total() }} {{ $resultados->total() === 1 ? 'resultado' : 'resultados' }}
                        @unless ($hayFiltros) (todos, del más reciente al más antiguo) @endunless
                    </span>
                </div>
            </form>

            <script>
                // "Hasta" nunca puede ser anterior a "desde" (y viceversa).
                (function () {
                    const desde = document.getElementById('f-desde');
                    const hasta = document.getElementById('f-hasta');
                    function ajustar() {
                        hasta.min = desde.value || '';
                        if (hasta.value) { desde.max = hasta.value; }
                        else { desde.max = '{{ now()->toDateString() }}'; }
                    }
                    desde.addEventListener('change', ajustar);
                    hasta.addEventListener('change', ajustar);
                    ajustar();
                })();
            </script>

            {{-- Resultados --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm text-gray-700 dark:text-gray-300">
                    @if ($tipo === 'cotizaciones')
                        <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                            <tr>
                                <th class="px-3 py-2">Número</th>
                                <th class="px-3 py-2">Institución</th>
                                <th class="px-3 py-2">Emisión</th>
                                <th class="px-3 py-2">Vence</th>
                                <th class="px-3 py-2 text-right">Total (Bs)</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Documentos</th>
                                @if ($esAdmin)<th class="px-3 py-2">Elaborada por</th>@endif
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resultados as $c)
                                <tr class="border-t dark:border-gray-700">
                                    <td class="px-3 py-2 font-medium whitespace-nowrap">{{ $c->numero_completo }}</td>
                                    <td class="px-3 py-2">
                                        {{ $c->cliente->razon_social }}
                                        <div class="text-xs text-gray-500">{{ $c->cliente->documento_identidad }}</div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $c->fecha_emision->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $c->fecha_vencimiento->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($c->total, 2) }}</td>
                                    <td class="px-3 py-2"><x-estado-cotizacion :cotizacion="$c" /></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs">
                                        @if ($c->recibos_activos || $c->facturas_activas)
                                            {{ $c->recibos_activos }} {{ $c->recibos_activos === 1 ? 'recibo' : 'recibos' }}
                                            · {{ $c->facturas_activas }} {{ $c->facturas_activas === 1 ? 'factura' : 'facturas' }}
                                        @else
                                            <span class="text-gray-400">Sin recibos ni facturas</span>
                                        @endif
                                    </td>
                                    @if ($esAdmin)<td class="px-3 py-2">{{ $c->creador->nombre }}</td>@endif
                                    <td class="px-3 py-2"><a href="{{ route('cotizaciones.show', $c) }}" class="text-indigo-600 hover:underline">Ver</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-3 py-6 text-center text-gray-500">No se encontraron cotizaciones con esos datos.</td></tr>
                            @endforelse
                        </tbody>

                    @elseif ($tipo === 'recibos')
                        <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                            <tr>
                                <th class="px-3 py-2">Recibo</th>
                                <th class="px-3 py-2">Cotización</th>
                                <th class="px-3 py-2">Institución</th>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Forma de pago</th>
                                <th class="px-3 py-2 text-right">Monto (Bs)</th>
                                <th class="px-3 py-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resultados as $r)
                                <tr class="border-t dark:border-gray-700">
                                    <td class="px-3 py-2 whitespace-nowrap"><a href="{{ route('recibos.show', $r) }}" class="text-indigo-600 hover:underline">{{ $r->numero_completo }}</a></td>
                                    <td class="px-3 py-2 whitespace-nowrap"><a href="{{ route('cotizaciones.show', $r->cotizacion) }}" class="text-indigo-600 hover:underline">{{ $r->cotizacion->numero_completo }}</a></td>
                                    <td class="px-3 py-2">
                                        {{ $r->cotizacion->cliente->razon_social }}
                                        <div class="text-xs text-gray-500">{{ $r->cotizacion->cliente->documento_identidad }}</div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $r->fecha_emision->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $r->forma_pago }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($r->monto, 2) }}</td>
                                    <td class="px-3 py-2"><x-estado-recibo :recibo="$r" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No se encontraron recibos con esos datos.</td></tr>
                            @endforelse
                        </tbody>

                    @else
                        <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                            <tr>
                                <th class="px-3 py-2">Factura</th>
                                <th class="px-3 py-2">Cotización</th>
                                <th class="px-3 py-2">Institución</th>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2 text-right">Monto (Bs)</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">SIN</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resultados as $fa)
                                <tr class="border-t dark:border-gray-700">
                                    <td class="px-3 py-2 whitespace-nowrap"><a href="{{ route('facturas.show', $fa) }}" class="text-indigo-600 hover:underline">{{ $fa->numero_completo }}</a></td>
                                    <td class="px-3 py-2 whitespace-nowrap"><a href="{{ route('cotizaciones.show', $fa->cotizacion) }}" class="text-indigo-600 hover:underline">{{ $fa->cotizacion->numero_completo }}</a></td>
                                    <td class="px-3 py-2">
                                        {{ $fa->cliente_razon_social }}
                                        <div class="text-xs text-gray-500">{{ $fa->cliente_documento }}</div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $fa->fecha_emision->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($fa->monto_total, 2) }}</td>
                                    <td class="px-3 py-2"><x-estado-factura :factura="$fa" /></td>
                                    <td class="px-3 py-2">{{ str_replace('_', ' ', $fa->estado_sin) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No se encontraron facturas con esos datos.</td></tr>
                            @endforelse
                        </tbody>
                    @endif
                </table>
            </div>

            {{ $resultados->links() }}
        </div>
    </div>
</x-app-layout>
