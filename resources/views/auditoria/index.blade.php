<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Auditoría</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Registro de quién hizo qué y cuándo en el sistema.</p>
    </x-slot>

    @php
        $colores = [
            'CREAR'      => 'bg-green-100 text-green-800',
            'ACTUALIZAR' => 'bg-blue-100 text-blue-800',
            'BAJA'       => 'bg-red-100 text-red-800',
            'REACTIVAR'  => 'bg-yellow-100 text-yellow-800',
            'LOGIN'      => 'bg-gray-100 text-gray-700',
            'LOGOUT'     => 'bg-gray-100 text-gray-700',
        ];

        $campo = 'rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200';

        // Convierte lo guardado (JSON o arreglo) en un arreglo para mostrar
        $aArreglo = function ($valor) {
            if (is_string($valor)) {
                $valor = json_decode($valor, true);
            }
            return is_array($valor) ? $valor : [];
        };

        $texto = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_null($v) ? '—' : (string) $v);
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">

            {{-- Filtros --}}
            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 text-sm shadow-sm dark:bg-gray-800">
                <div>
                    <label class="mb-1 block text-gray-700 dark:text-gray-300">Usuario</label>
                    <select name="usuario" class="{{ $campo }}">
                        <option value="">Todos</option>
                        @foreach ($usuarios as $u)
                            <option value="{{ $u->id_usuario }}" @selected(($filtros['usuario'] ?? '') == $u->id_usuario)>{{ $u->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-gray-700 dark:text-gray-300">Acción</label>
                    <select name="accion" class="{{ $campo }}">
                        <option value="">Todas</option>
                        @foreach ($acciones as $a)
                            <option value="{{ $a }}" @selected(($filtros['accion'] ?? '') === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-gray-700 dark:text-gray-300">Tabla</label>
                    <select name="tabla" class="{{ $campo }}">
                        <option value="">Todas</option>
                        @foreach ($tablas as $t)
                            <option value="{{ $t }}" @selected(($filtros['tabla'] ?? '') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-gray-700 dark:text-gray-300">Desde</label>
                    <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="{{ $campo }}">
                </div>

                <div>
                    <label class="mb-1 block text-gray-700 dark:text-gray-300">Hasta</label>
                    <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="{{ $campo }}">
                </div>

                <button class="rounded-md bg-[#0c3082] px-4 py-2 font-semibold text-white hover:bg-[#0a2668]">Filtrar</button>
                <a href="{{ route('auditoria.index') }}" class="py-2 text-gray-500 hover:underline dark:text-gray-400">Limpiar</a>
            </form>

            {{-- Registros --}}
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm dark:bg-gray-800">
                <table class="min-w-full text-sm text-gray-700 dark:text-gray-300">
                    <thead class="bg-gray-50 text-left dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2">Fecha y hora</th>
                            <th class="px-3 py-2">Usuario</th>
                            <th class="px-3 py-2">Acción</th>
                            <th class="px-3 py-2">Tabla</th>
                            <th class="px-3 py-2">Registro</th>
                            <th class="px-3 py-2">IP</th>
                            <th class="px-3 py-2">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registros as $r)
                            @php
                                $antes = $aArreglo($r->datos_anteriores);
                                $despues = $aArreglo($r->datos_nuevos);

                                // Para ACTUALIZAR solo se muestran los campos que cambiaron
                                $campos = array_unique(array_merge(array_keys($antes), array_keys($despues)));
                                if ($r->accion === 'ACTUALIZAR') {
                                    $campos = array_filter($campos, fn ($c) => ($antes[$c] ?? null) !== ($despues[$c] ?? null));
                                }
                            @endphp

                            <tr class="border-t align-top dark:border-gray-700">
                                <td class="whitespace-nowrap px-3 py-2">{{ \Carbon\Carbon::parse($r->fecha_hora)->format('d/m/Y H:i:s') }}</td>
                                <td class="px-3 py-2">{{ $r->usuario_nombre ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $colores[$r->accion] ?? 'bg-gray-100 text-gray-700' }}">{{ $r->accion }}</span>
                                </td>
                                <td class="px-3 py-2">{{ $r->tabla_afectada ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $r->id_registro ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $r->ip ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @if (count($campos) > 0)
                                        <details>
                                            <summary class="cursor-pointer text-[#0c3082] hover:underline dark:text-blue-300">
                                                Ver ({{ count($campos) }})
                                            </summary>
                                            <table class="mt-2 text-xs">
                                                <thead>
                                                    <tr class="text-left text-gray-500">
                                                        <th class="pr-4">Campo</th>
                                                        <th class="pr-4">Antes</th>
                                                        <th>Después</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($campos as $c)
                                                        <tr>
                                                            <td class="pr-4 font-medium">{{ $c }}</td>
                                                            <td class="pr-4 text-red-600">{{ $texto($antes[$c] ?? null) }}</td>
                                                            <td class="text-green-700 dark:text-green-400">{{ $texto($despues[$c] ?? null) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </details>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-8 text-center text-gray-500">No hay registros con esos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $registros->links() }}
        </div>
    </div>
</x-app-layout>
