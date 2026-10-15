@php
    $usuario = auth()->user();
    $esAdmin = (int) $usuario->id_rol === 1;

    // Pendientes de aprobación (solo se calculan para el administrador)
    $pendientes = ['cotizaciones.index' => 0, 'recibos.index' => 0, 'facturas.index' => 0];
    if ($esAdmin) {
        $pendientes['cotizaciones.index'] = \App\Modules\Cotizaciones\Models\Cotizacion::where('estado', 'PENDIENTE')->count();
        $pendientes['recibos.index']      = \App\Modules\Recibos\Models\Recibo::where('estado', 'PENDIENTE')->count();
        $pendientes['facturas.index']     = \App\Modules\Facturas\Models\Factura::where('estado', 'PENDIENTE')->count();
    }

    $iconos = [
        'home'     => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        'documento'=> 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
        'dinero'   => 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z',
        'factura'  => 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
        'clientes' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
        'producto' => 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
        'etiqueta' => 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3zM6 6h.008v.008H6V6z',
        'empresa'  => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z',
        'usuarios' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
        'escudo'   => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
    ];

    // [nombre, ruta, patrón de ruta activa, ícono]
    $grupos = [
        'Principal' => [
            ['Panel', 'dashboard', 'dashboard', 'home'],
        ],
        'Ventas' => [
            ['Cotizaciones', 'cotizaciones.index', 'cotizaciones.*', 'documento'],
            ['Recibos', 'recibos.index', 'recibos.*', 'dinero'],
            ['Facturas', 'facturas.index', 'facturas.*', 'factura'],
        ],
        'Catálogo' => [
            ['Clientes', 'clientes.index', 'clientes.*', 'clientes'],
            ['Productos', 'productos.index', 'productos.*', 'producto'],
            ['Categorías', 'categorias.index', 'categorias.*', 'etiqueta'],
        ],
    ];

    if ($esAdmin) {
        $grupos['Administración'] = [
            ['Empresa', 'empresa.edit', 'empresa.*', 'empresa'],
            ['Usuarios', 'usuarios.index', 'usuarios.*', 'usuarios'],
            ['Auditoría', 'auditoria.index', 'auditoria.*', 'escudo'],
        ];
    }
@endphp

{{-- Fondo oscuro al abrir el menú en celular --}}
<div x-show="menu" x-cloak @click="menu = false" class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

<aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-[#0c3082] transition-transform duration-200 lg:translate-x-0">

    {{-- Marca --}}
    <div class="flex h-16 items-center justify-between border-b border-white/10 px-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white p-0.5">
                <x-application-logo class="h-9 w-9 object-contain" />
            </span>
            <span class="leading-tight">
                <span class="block text-base font-bold tracking-wide text-white">{{ config('app.name') }}</span>
                <span class="block text-[11px] text-blue-200/70">Gestión de cotizaciones</span>
            </span>
        </a>

        <button @click="menu = false" class="rounded-md p-1 text-blue-100 hover:bg-white/10 lg:hidden" aria-label="Cerrar menú">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Módulos --}}
        <nav class="menu-scroll flex-1 space-y-4 overflow-y-auto px-3 py-3">
        @foreach ($grupos as $titulo => $items)
            @php $visibles = collect($items)->filter(fn ($item) => Route::has($item[1])); @endphp

            @if ($visibles->isNotEmpty())
                <div>
                    <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-blue-200/60">{{ $titulo }}</p>

                    <div class="space-y-0.5">
                        @foreach ($visibles as $item)
                            @php
                                [$nombre, $ruta, $patron, $icono] = $item;
                                $activo = request()->routeIs($patron);
                                $cantidad = $pendientes[$ruta] ?? 0;
                            @endphp

                            <a href="{{ route($ruta) }}"
                               class="flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm font-medium transition
                                      {{ $activo ? 'bg-white/15 text-white' : 'text-blue-100/80 hover:bg-white/10 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconos[$icono] }}" />
                                </svg>
                                <span class="flex-1">{{ $nombre }}</span>

                                @if ($cantidad > 0)
                                    <span class="rounded-full bg-red-500 px-2 py-0.5 text-[11px] font-semibold text-white"
                                          title="Pendientes de aprobación">{{ $cantidad }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Usuario --}}
    <div class="border-t border-white/10 p-3">
        <div class="flex items-center gap-3 px-3 py-1.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-sm font-bold text-white">
                {{ mb_strtoupper(mb_substr($usuario->nombre ?? 'U', 0, 1)) }}
            </span>
            <span class="min-w-0 leading-tight">
                <span class="block truncate text-sm font-semibold text-white">{{ $usuario->nombre }}</span>
                <span class="block text-[11px] text-blue-200/70">{{ $esAdmin ? 'Administrador' : 'Vendedor' }}</span>
            </span>
        </div>

        <div class="mt-1 space-y-0.5">
            @if (Route::has('profile.edit'))
                <a href="{{ route('profile.edit') }}"
                   class="block rounded-lg px-3 py-2 text-sm text-blue-100/80 hover:bg-white/10 hover:text-white">
                    Mi perfil
                </a>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="block w-full rounded-lg px-3 py-2 text-left text-sm text-blue-100/80 hover:bg-white/10 hover:text-white">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</aside>
