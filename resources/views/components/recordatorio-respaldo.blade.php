@php
    $mostrar = false;
    $dias = null;

    // Solo para administradores, y no en la propia pantalla de respaldos (ahí ya se explica el estado).
    if (auth()->check() && auth()->user()->id_rol == 1 && ! request()->routeIs('respaldos.*')) {
        try {
            $ultima = \Illuminate\Support\Facades\DB::table('respaldo_descargas')->max('created_at');
            $dias = $ultima
                ? intdiv(max(0, now()->timestamp - \Illuminate\Support\Carbon::parse($ultima)->timestamp), 86400)
                : null;
            $mostrar = $ultima === null || $dias >= 7;
        } catch (\Throwable $e) {
            $mostrar = false;
        }
    }
@endphp

@if ($mostrar)
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <div class="flex flex-wrap items-center gap-3 rounded-md border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-100">
            <div class="flex-1 min-w-[16rem]">
                <p class="font-semibold">Recuerda guardar una copia de seguridad</p>
                <p>
                    @if ($dias === null)
                        Aún no has descargado ninguna copia de la base de datos.
                    @else
                        Hace {{ $dias }} días que no descargas una copia de la base de datos.
                    @endif
                </p>
            </div>
            <a href="{{ route('respaldos.index') }}"
               class="rounded-md bg-yellow-600 px-4 py-2 font-semibold text-white hover:bg-yellow-700">
                Descargar copia
            </a>
        </div>
    </div>
@endif
