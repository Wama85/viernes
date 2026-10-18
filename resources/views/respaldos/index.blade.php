<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Copias de seguridad</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            {{-- Estado de la última copia descargada --}}
            @if ($alDia)
                <div class="rounded-md border border-green-300 bg-green-50 p-4 text-sm text-green-900">
                    <p class="font-semibold">Tienes una copia reciente</p>
                    <p>
                        @if ($dias === 0)
                            Descargaste una copia hoy.
                        @else
                            Descargaste una copia hace {{ $dias }} {{ $dias === 1 ? 'día' : 'días' }}.
                        @endif
                        Te volveremos a recordar cuando pasen 7 días.
                    </p>
                </div>
            @else
                <div class="rounded-md border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900">
                    <p class="font-semibold">Falta descargar una copia</p>
                    <p>
                        @if ($dias === null)
                            Aún no se ha descargado ninguna copia de seguridad.
                        @else
                            Hace {{ $dias }} días que no descargas una copia.
                        @endif
                        Descarga una y guárdala fuera del servidor (tu computadora o una unidad externa).
                    </p>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        El servidor crea una copia nueva cada 7 días y conserva las últimas {{ $conservar }}.
                    </p>

                    <div x-data="copiaSeguridad(@js(route('respaldos.crear')))" class="text-right">
                        <button type="button" @click="crear()" :disabled="cargando"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-60">
                            <span x-show="!cargando">Crear y descargar copia</span>
                            <span x-show="cargando" x-cloak>Preparando la copia…</span>
                        </button>
                        <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-red-600"></p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm text-gray-700 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                            <tr>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Archivo</th>
                                <th class="px-3 py-2 text-right">Tamaño</th>
                                <th class="px-3 py-2 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($archivos as $a)
                                <tr class="border-t dark:border-gray-700">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $a['fecha']->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2">
                                        <span class="break-all">{{ $a['nombre'] }}</span>
                                        @if ($a['protegido'])
                                            <span class="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-800">con contraseña</span>
                                        @else
                                            <span class="ml-1 rounded bg-yellow-100 px-1.5 py-0.5 text-xs text-yellow-800">sin contraseña</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ number_format($a['mb'], 2) }} MB</td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('respaldos.bajar', $a['nombre']) }}"
                                           class="text-indigo-600 hover:underline font-medium">Descargar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-gray-500">
                                        Todavía no hay copias. Pulsa «Crear respaldo ahora».
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 rounded-md bg-gray-50 dark:bg-gray-900 p-3 text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <p>Los archivos .zip están cifrados con la contraseña de respaldos del sistema. Ábrelos con 7-Zip o WinRAR.</p>
                    <p>Para restaurar: descomprime el .zip y, en phpMyAdmin, usa Importar con el archivo .sql.</p>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('copiaSeguridad', (url) => ({
                url,
                cargando: false,
                error: '',

                async crear() {
                    if (this.cargando) return;
                    this.cargando = true;
                    this.error = '';

                    try {
                        const r = await fetch(this.url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!r.ok) {
                            let mensaje = 'No se pudo crear la copia. Intenta de nuevo.';
                            try { mensaje = (await r.json()).mensaje || mensaje; } catch (e) {}
                            throw new Error(mensaje);
                        }

                        // Se guarda el archivo y luego se refresca la pantalla (lista y estado al día).
                        const blob = await r.blob();
                        const m = /filename="?([^";]+)"?/.exec(r.headers.get('Content-Disposition') || '');
                        const enlace = document.createElement('a');
                        enlace.href = URL.createObjectURL(blob);
                        enlace.download = m ? m[1] : 'respaldo.zip';
                        document.body.appendChild(enlace);
                        enlace.click();
                        enlace.remove();

                        setTimeout(() => {
                            URL.revokeObjectURL(enlace.href);
                            window.location.reload();
                        }, 1200);
                    } catch (e) {
                        this.error = e.message;
                        this.cargando = false;
                    }
                },
            }));
        });
    </script>
</x-app-layout>
