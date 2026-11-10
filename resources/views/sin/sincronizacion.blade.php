<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Sincronización con el SIN
            </h2>
            <span class="text-xs px-2 py-1 rounded {{ $ambiente === 2 ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                {{ $ambiente === 2 ? 'Ambiente de pruebas (piloto)' : 'Ambiente de producción' }}
            </span>
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

            {{-- Códigos vigentes --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 text-sm text-gray-700 dark:text-gray-300">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Códigos de facturación</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <p class="text-gray-500">CUIS</p>
                        @if ($cuis)
                            <p class="font-semibold text-green-700 dark:text-green-400">Vigente hasta {{ $cuis->fecha_vigencia->format('d/m/Y H:i') }}</p>
                        @else
                            <p class="font-semibold text-red-600">Sin CUIS vigente</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-gray-500">CUFD</p>
                        @if ($cufd)
                            <p class="font-semibold text-green-700 dark:text-green-400">Vigente hasta {{ $cufd->fecha_vigencia->format('d/m/Y H:i') }}</p>
                        @else
                            <p class="font-semibold text-red-600">Sin CUFD vigente</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Catálogos --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-4 text-sm text-gray-700 dark:text-gray-300">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Catálogos y parámetros</h3>
                        <p class="text-xs text-gray-500">Actividades, leyendas, productos y servicios, métodos de pago, eventos significativos, etc.</p>
                    </div>

                    <form method="POST" action="{{ route('sin.sincronizacion.ejecutar') }}"
                          x-data="{ enviando: false }" @submit="enviando = true">
                        @csrf
                        <button type="submit" :disabled="enviando"
                                class="px-4 py-2 bg-[#0c3082] text-white rounded-md hover:bg-[#0a2668] disabled:opacity-60">
                            <span x-show="!enviando">Sincronizar todo</span>
                            <span x-show="enviando" x-cloak>Sincronizando… (puede tardar un minuto)</span>
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                            <tr>
                                <th class="px-3 py-2">Catálogo</th>
                                <th class="px-3 py-2">Última sincronización</th>
                                <th class="px-3 py-2 text-right">Registros</th>
                                <th class="px-3 py-2">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($catalogos as $c)
                                @php $r = $resultados[$c['nombre']] ?? null; @endphp
                                <tr class="border-t dark:border-gray-700">
                                    <td class="px-3 py-2">{{ $c['nombre'] }}</td>
                                    <td class="px-3 py-2">
                                        @if ($c['sincronizado'])
                                            {{ $c['sincronizado']->format('d/m/Y H:i') }}
                                            <span class="text-xs text-gray-500">({{ $c['sincronizado']->diffForHumans() }})</span>
                                        @else
                                            <span class="text-red-600">Nunca</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ $c['registros'] ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($r === null)
                                            <span class="text-gray-400">—</span>
                                        @elseif ($r['ok'])
                                            <span class="text-green-700 dark:text-green-400">✓ Correcto</span>
                                        @else
                                            <span class="text-red-600" title="{{ $r['mensaje'] }}">✕ Falló: {{ \Illuminate\Support\Str::limit($r['mensaje'], 80) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
