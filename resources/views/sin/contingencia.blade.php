<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Contingencia (facturas fuera de línea)
            </h2>
            @if ($conexion)
                <span class="text-xs px-2 py-1 rounded" style="background-color:#dcfce7; color:#166534;">Con conexión al SIN</span>
            @else
                <span class="text-xs px-2 py-1 rounded" style="background-color:#fee2e2; color:#991b1b;">Sin conexión al SIN</span>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 rounded bg-red-100 text-red-800">{{ session('error') }}</div>
            @endif

            @if (! $conexion && $motivoCaida)
                <div class="p-3 rounded text-sm" style="background-color:#fee2e2; color:#991b1b;">
                    No hay comunicación con el SIN: {{ $motivoCaida }}
                </div>
            @endif

            {{-- Pasos del último cierre --}}
            @if ($pasos)
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 text-sm text-gray-700 dark:text-gray-300 space-y-1">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Resultado del cierre</h3>
                    @foreach ($pasos as $paso)
                        <p @style(['color: #166534' => $paso['ok'], 'color: #991b1b' => ! $paso['ok']])>
                            {{ $paso['ok'] ? '✓' : '✕' }} {{ $paso['texto'] }}
                        </p>
                    @endforeach
                </div>
            @endif

            {{-- Simulación (solo pruebas) --}}
            @if ($esPiloto)
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 text-sm text-gray-700 dark:text-gray-300">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Simular falta de conexión</h3>
                            <p class="text-xs text-gray-500">Solo en el ambiente de pruebas: las facturas nuevas se emiten fuera de línea aunque haya internet.</p>
                        </div>
                        <form method="POST" action="{{ route('sin.contingencia.simular') }}">
                            @csrf
                            <input type="hidden" name="activar" value="{{ $simulando ? 0 : 1 }}">
                            <button type="submit" class="px-4 py-2 rounded-md text-white"
                                    @style(['background-color: #dc2626' => $simulando, 'background-color: #4b5563' => ! $simulando])>
                                {{ $simulando ? 'Desactivar simulación (activa)' : 'Activar simulación' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Pendientes --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-4 text-sm text-gray-700 dark:text-gray-300">
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Facturas emitidas fuera de línea pendientes</h3>
                    <p class="text-xs text-gray-500">Se envían al SIN en un paquete, después de registrar el evento significativo.</p>
                </div>

                @if ($pendientes->isEmpty())
                    <p class="text-gray-500">No hay facturas pendientes. Todo está al día con el SIN.</p>
                @else
                    @if ($plazo)
                        @php $urgente = $plazo->diffInHours(now(), false) > -12; @endphp
                        <p class="p-2 rounded text-sm"
                           @style(['color: #78350f', 'background-color: #fee2e2' => $urgente, 'background-color: #fffbeb' => ! $urgente])>
                            El SIN da {{ \App\Modules\Sin\Services\SinContingenciaService::PLAZO_HORAS }} horas para enviar el paquete.
                            Plazo: <strong>{{ $plazo->format('d/m/Y H:i') }}</strong>
                            ({{ $plazo->isPast() ? 'vencido' : 'faltan ' . $plazo->diffForHumans(null, true) }}).
                        </p>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                                <tr>
                                    <th class="px-3 py-2">Factura</th>
                                    <th class="px-3 py-2">Emisión</th>
                                    <th class="px-3 py-2">Cliente</th>
                                    <th class="px-3 py-2 text-right">Monto (Bs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendientes as $f)
                                    <tr class="border-t dark:border-gray-700">
                                        <td class="px-3 py-2">
                                            <a href="{{ route('facturas.show', $f) }}" class="text-indigo-600 hover:underline">{{ $f->numero_completo }}</a>
                                        </td>
                                        <td class="px-3 py-2">{{ $f->fecha_emision->format('d/m/Y H:i') }}</td>
                                        <td class="px-3 py-2">{{ $f->cliente_razon_social }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($f->monto_total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('sin.contingencia.cerrar') }}" class="flex flex-wrap items-end gap-3"
                          @submit.prevent="$dispatch('confirmar-accion', {
                              form: $el,
                              titulo: 'Cerrar la contingencia',
                              texto: 'Se pedirá un CUFD nuevo, se registrará el evento significativo en el SIN y se enviarán {{ $pendientes->count() }} factura(s) en un paquete. Esto no se puede deshacer.',
                              boton: 'Cerrar y enviar'
                          })">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-400" for="motivo">Motivo del evento</label>
                            <select id="motivo" name="motivo"
                                    class="mt-1 block rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                                @foreach ($motivos as $codigo => $descripcion)
                                    <option value="{{ $codigo }}" @selected($codigo === 2)>{{ $codigo }} · {{ \Illuminate\Support\Str::limit($descripcion, 70) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" @disabled(! $conexion)
                                class="px-4 py-2 rounded-md text-white"
                                @style(['background-color: #0c3082' => $conexion, 'background-color: #9ca3af' => ! $conexion])>
                            Cerrar contingencia y enviar paquete
                        </button>
                        @unless ($conexion)
                            <p class="text-xs" style="color:#991b1b;">Todavía no hay conexión con el SIN.</p>
                        @endunless
                    </form>
                @endif
            </div>

            {{-- Historial --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">Eventos y paquetes recientes</h3>

                @if ($eventos->isEmpty())
                    <p class="text-gray-500">Todavía no se registró ningún evento.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                                <tr>
                                    <th class="px-3 py-2">Evento</th>
                                    <th class="px-3 py-2">Periodo</th>
                                    <th class="px-3 py-2">Paquete</th>
                                    <th class="px-3 py-2">Estado</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($eventos as $e)
                                    @forelse ($e->paquetes as $p)
                                        <tr class="border-t dark:border-gray-700">
                                            <td class="px-3 py-2">
                                                #{{ $e->codigo_recepcion }} · motivo {{ $e->codigo_motivo }}
                                                <div class="text-xs text-gray-500">{{ $e->estado }}</div>
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ $e->fecha_inicio->format('d/m/Y H:i:s') }}<br>
                                                <span class="text-xs text-gray-500">a {{ $e->fecha_fin->format('d/m/Y H:i:s') }}</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ $p->cantidad_facturas }} factura(s)
                                                <div class="text-xs text-gray-500">FAC-{{ sprintf('%06d', $p->numero_inicio) }} a FAC-{{ sprintf('%06d', $p->numero_fin) }}</div>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span @style(['color: #166534' => $p->estado === 'VALIDADO', 'color: #991b1b' => $p->estado === 'RECHAZADO', 'color: #92400e' => ! in_array($p->estado, ['VALIDADO', 'RECHAZADO'], true)])>
                                                    {{ $p->estado }}
                                                </span>
                                                @if ($p->mensajes)
                                                    <div class="text-xs text-gray-500" title="{{ $p->mensajes }}">{{ \Illuminate\Support\Str::limit($p->mensajes, 70) }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                @if ($p->estado === 'ENVIADO')
                                                    <form method="POST" action="{{ route('sin.contingencia.validar', $p) }}">
                                                        @csrf
                                                        <button type="submit" class="text-indigo-600 hover:underline">Consultar validación</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="border-t dark:border-gray-700">
                                            <td class="px-3 py-2">#{{ $e->codigo_recepcion ?? '—' }} · motivo {{ $e->codigo_motivo }}<div class="text-xs text-gray-500">{{ $e->estado }}</div></td>
                                            <td class="px-3 py-2">{{ $e->fecha_inicio->format('d/m/Y H:i:s') }}<br><span class="text-xs text-gray-500">a {{ $e->fecha_fin->format('d/m/Y H:i:s') }}</span></td>
                                            <td class="px-3 py-2 text-gray-400" colspan="3">Sin paquete{{ $e->mensajes ? ' · ' . \Illuminate\Support\Str::limit($e->mensajes, 80) : '' }}</td>
                                        </tr>
                                    @endforelse
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <x-modal-confirmacion />
</x-app-layout>
