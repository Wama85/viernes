@php
    $p = $producto ?? null;
    $inputClase = 'block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm';
    $numero = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp

<p class="text-sm text-gray-600 dark:text-gray-400">
    Los campos marcados con <span class="text-red-600 font-bold">*</span> son obligatorios.
</p>

{{-- Producto del SIN (con buscador) --}}
<div x-data="selectorProductoSin(@js($opcionesSin), @js((string) old('id_producto_sin', $p->id_producto_sin ?? '')))"
     @click.outside="cerrar()"
     class="relative">
    <label for="producto_sin_buscar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        Producto del SIN <span class="text-red-600">*</span>
    </label>

    <input type="hidden" name="id_producto_sin" :value="valor">

    <div class="relative">
        <input id="producto_sin_buscar" type="text" x-ref="entrada" autocomplete="off"
               :placeholder="etiquetaActual || 'Escribe para buscar: hosting, desarrollo, mantenimiento, 83131...'"
               :value="abierto ? busqueda : etiquetaActual"
               @focus="abrir()"
               @input="buscar($event.target.value)"
               @keydown.arrow-down.prevent="mover(1)"
               @keydown.arrow-up.prevent="mover(-1)"
               @keydown.enter.prevent="elegirActual()"
               @keydown.escape="cerrar()"
               @keydown.tab="cerrar()"
               class="{{ $inputClase }} pr-9">

        <button type="button" x-show="valor" x-cloak @click="limpiar()" aria-label="Quitar selección"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600">
            &times;
        </button>
    </div>

    <div x-show="abierto" x-cloak x-ref="lista"
         class="absolute z-30 mt-1 w-full max-h-72 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
        <template x-for="(o, i) in visibles" :key="o.id">
            <button type="button" @click="elegir(o)" @mouseenter="indice = i"
                    :data-activo="i === indice"
                    :class="i === indice ? 'bg-indigo-50 dark:bg-gray-700' : ''"
                    class="block w-full px-3 py-2 text-left">
                <span class="block text-sm font-medium text-gray-800 dark:text-gray-100" x-text="o.nombre"></span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">
                    Código <span x-text="o.codigo"></span> · Actividad <span x-text="o.actividad"></span>
                    <span x-show="o.usado" class="text-amber-600"> · ★ usado antes</span>
                </span>
            </button>
        </template>

        <p x-show="visibles.length === 0" class="px-3 py-2 text-sm text-gray-500">Sin resultados.</p>
        <p x-show="total > visibles.length" class="px-3 py-2 text-xs text-gray-500 border-t border-gray-100 dark:border-gray-700">
            Mostrando los primeros <span x-text="visibles.length"></span> de <span x-text="total"></span>. Escribe más para afinar.
        </p>
    </div>

    @error('id_producto_sin') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- Categoría --}}
    <div>
        <label for="id_categoria" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Categoría <span class="text-red-600">*</span>
        </label>
        <select id="id_categoria" name="id_categoria" required class="{{ $inputClase }}">
            <option value="">-- Seleccione --</option>
            @foreach ($categorias as $c)
                <option value="{{ $c->id_categoria }}"
                    @selected(old('id_categoria', $p->id_categoria ?? '') == $c->id_categoria)>
                    {{ $c->nombre_categoria }}
                </option>
            @endforeach
        </select>
        @error('id_categoria') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Unidad de medida --}}
    <div>
        <label for="id_unidad_medida" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Unidad de medida <span class="text-red-600">*</span>
        </label>
        <select id="id_unidad_medida" name="id_unidad_medida" required class="{{ $inputClase }}">
            @foreach ($unidades as $u)
                <option value="{{ $u->id_unidad_medida }}"
                    @selected(old('id_unidad_medida', $p->id_unidad_medida ?? $unidadPorDefecto) == $u->id_unidad_medida)>
                    {{ $u->nombre_unidad_medida }}
                </option>
            @endforeach
        </select>
        @error('id_unidad_medida') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

{{-- Descripción --}}
<div>
    <label for="descripcion_producto" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        Descripción <span class="text-red-600">*</span>
    </label>
    <input id="descripcion_producto" name="descripcion_producto" type="text" maxlength="500" required
           value="{{ old('descripcion_producto', $p->descripcion_producto ?? '') }}"
           class="{{ $inputClase }} uppercase">
    @error('descripcion_producto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

{{-- Detalle --}}
<div>
    <label for="detalle" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        Detalle (medidas, capacidad, características)
    </label>
    <textarea id="detalle" name="detalle" rows="3" class="{{ $inputClase }}">{{ old('detalle', $p->detalle ?? '') }}</textarea>
    @error('detalle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

{{-- Imagen --}}
<div>
    <label for="imagen" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Imagen</label>
    @if ($p && $p->imagen)
        <img src="{{ asset('storage/' . $p->imagen) }}" alt="Imagen actual" class="h-24 rounded mb-2">
    @endif
    <input id="imagen" name="imagen" type="file" accept=".jpg,.jpeg,.png,.webp"
           class="block mt-1 w-full text-sm text-gray-700 dark:text-gray-300">
    <p class="text-xs text-gray-500">JPG, PNG o WEBP, máximo 2 MB.</p>
    @error('imagen') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- Costo --}}
    <div>
        <label for="costo_compra" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Costo de compra (Bs)</label>
        <input id="costo_compra" name="costo_compra" type="number" step="0.01" min="0"
               value="{{ old('costo_compra', $p->costo_compra ?? '0.00') }}" class="{{ $inputClase }}">
        @error('costo_compra') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Precio --}}
    <div>
        <label for="precio_venta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Precio de venta (Bs) <span class="text-red-600">*</span>
        </label>
        <input id="precio_venta" name="precio_venta" type="number" step="0.01" min="0" required
               value="{{ old('precio_venta', $p->precio_venta ?? '') }}" class="{{ $inputClase }}">
        @error('precio_venta') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

{{-- Stock y vencimiento --}}
<div x-data="{ controla: {{ old('controla_stock', $p ? (int) $p->controla_stock : 1) ? 'true' : 'false' }} }"
     class="space-y-4">
    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input type="hidden" name="controla_stock" value="0">
        <input type="checkbox" name="controla_stock" value="1" x-model="controla"
               class="rounded border-gray-300 dark:border-gray-700">
        Controlar stock (se descuenta al aprobar un recibo o factura)
    </label>
    <p class="text-xs text-gray-500">Déjalo sin marcar para servicios.</p>

    <div x-show="controla" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            @if ($p)
                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stock actual</span>
                <p class="mt-2 text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $numero($p->stock) }}</p>
                <a href="{{ route('productos.movimientos', $p) }}" class="text-sm text-indigo-600 hover:underline">
                    Registrar ingreso o ajuste
                </a>
            @else
                <label for="stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stock inicial</label>
                <input id="stock" name="stock" type="number" step="0.01" min="0"
                       value="{{ old('stock', '0') }}" class="{{ $inputClase }}">
                @error('stock') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @endif
        </div>

        <div>
            <label for="stock_minimo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stock mínimo (alerta)</label>
            <input id="stock_minimo" name="stock_minimo" type="number" step="0.01" min="0"
                   value="{{ old('stock_minimo', $p->stock_minimo ?? '0') }}" class="{{ $inputClase }}">
            <p class="text-xs text-gray-500">Te avisamos en el panel cuando llegue a este nivel.</p>
            @error('stock_minimo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="fecha_vencimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de vencimiento</label>
            <input id="fecha_vencimiento" name="fecha_vencimiento" type="date"
                   value="{{ old('fecha_vencimiento', optional($p?->fecha_vencimiento)->format('Y-m-d')) }}"
                   class="{{ $inputClase }}">
            @error('fecha_vencimiento') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        const normalizar = (t) => String(t).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

        Alpine.data('selectorProductoSin', (opciones, seleccionado) => ({
            opciones: opciones.map((o) => ({ ...o, buscable: normalizar(o.codigo + ' ' + o.actividad + ' ' + o.nombre) })),
            valor: seleccionado,
            abierto: false,
            busqueda: '',
            indice: 0,
            visibles: [],
            total: 0,

            init() {
                this.$nextTick(() => this.validar());
            },

            get etiquetaActual() {
                const o = this.opciones.find((x) => String(x.id) === String(this.valor));
                return o ? o.nombre + ' (' + o.codigo + ')' : '';
            },

            filtrar() {
                const palabras = normalizar(this.busqueda).split(/\s+/).filter(Boolean);
                const coincidencias = palabras.length
                    ? this.opciones.filter((o) => palabras.every((p) => o.buscable.includes(p)))
                    : this.opciones;
                this.total = coincidencias.length;
                this.visibles = coincidencias.slice(0, 50);
            },

            abrir() {
                this.abierto = true;
                this.busqueda = '';
                this.indice = 0;
                this.filtrar();
            },

            buscar(texto) {
                this.abierto = true;
                this.busqueda = texto;
                this.indice = 0;
                this.filtrar();
            },

            mover(paso) {
                if (!this.abierto) { this.abrir(); return; }
                const n = this.visibles.length;
                if (!n) return;
                this.indice = (this.indice + paso + n) % n;
                this.$nextTick(() => {
                    const activo = this.$refs.lista.querySelector('[data-activo="true"]');
                    if (activo) activo.scrollIntoView({ block: 'nearest' });
                });
            },

            elegirActual() {
                const o = this.visibles[this.indice];
                if (o) this.elegir(o);
            },

            elegir(o) {
                this.valor = String(o.id);
                this.cerrar();
                this.validar();
            },

            limpiar() {
                this.valor = '';
                this.validar();
                this.$refs.entrada.focus();
            },

            cerrar() {
                this.abierto = false;
                this.busqueda = '';
            },

            validar() {
                this.$refs.entrada.setCustomValidity(this.valor ? '' : 'Selecciona un producto del SIN de la lista.');
            },
        }));
    });
</script>
