@php
    $p = $producto ?? null;
    $inputClase = 'block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm';
@endphp

<p class="text-sm text-gray-600 dark:text-gray-400">
    Los campos marcados con <span class="text-red-600 font-bold">*</span> son obligatorios.
</p>

{{-- Producto del SIN --}}
<div>
    <label for="id_producto_sin" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        Producto del SIN <span class="text-red-600">*</span>
    </label>
    <select id="id_producto_sin" name="id_producto_sin" required class="{{ $inputClase }}">
        <option value="">-- Seleccione --</option>
        @foreach ($productosSin as $ps)
            <option value="{{ $ps->id_producto_sin }}"
                @selected(old('id_producto_sin', $p->id_producto_sin ?? '') == $ps->id_producto_sin)>
                {{ $ps->codigo_producto_sin }} · {{ $ps->codigo_actividad }} · {{ $ps->descripcion_producto_sin }}
            </option>
        @endforeach
    </select>
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
        Controlar stock (se descuenta al facturar)
    </label>

    <div x-show="controla" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stock actual</label>
            <input id="stock" name="stock" type="number" step="0.01" min="0"
                   value="{{ old('stock', $p->stock ?? '0') }}" class="{{ $inputClase }}">
            @error('stock') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
