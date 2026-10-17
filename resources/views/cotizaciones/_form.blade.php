@php
    $c = $cotizacion ?? null;
    $bloqueoItems = $c && $c->tieneRecibos();
    $inputClase = 'block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm';
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

    $listaClientes = $clientes->map(fn ($x) => [
        'id'    => $x->id_cliente,
        'texto' => trim($x->documento_identidad . ($x->complemento ? '-' . $x->complemento : '')) . ' · ' . $x->razon_social,
    ])->values();

    $listaProductos = $productos->map(fn ($p) => [
        'id'          => $p->id_producto,
        'descripcion' => $p->descripcion_producto,
        'unidad'      => $p->unidadMedida->nombre_unidad_medida,
        'precio'      => (float) $p->precio_venta,
        'stock'       => $p->controla_stock ? (float) $p->stock : null,
    ])->values();

    $lineaVacia = ['id_producto' => '', 'cantidad' => 1, 'precio_unitario' => '', 'descuento' => 0, 'descripcion_adicional' => ''];

    if (old('items')) {
        $itemsIniciales = collect(old('items'))->values()->map(fn ($i) => [
            'id_producto'           => $i['id_producto'] ?? '',
            'cantidad'              => $i['cantidad'] ?? 1,
            'precio_unitario'       => $i['precio_unitario'] ?? '',
            'descuento'             => $i['descuento'] ?? 0,
            'descripcion_adicional' => $i['descripcion_adicional'] ?? '',
        ])->all();
    } elseif ($c) {
        $itemsIniciales = $c->detalles->map(fn ($d) => [
            'id_producto'           => $d->id_producto,
            'cantidad'              => (float) $d->cantidad,
            'precio_unitario'       => (float) $d->precio_unitario,
            'descuento'             => (float) $d->descuento,
            'descripcion_adicional' => $d->descripcion_adicional ?? '',
        ])->values()->all();
    } else {
        $itemsIniciales = [$lineaVacia];
    }
@endphp

@if ($errors->any())
    <div class="p-3 rounded bg-red-100 text-red-800 text-sm">
        <ul class="list-disc ml-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Aviso de stock insuficiente (no bloquea) --}}
@if (session('advertencia_stock'))
    <div class="rounded-md border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900">
        <p class="font-semibold">Stock insuficiente</p>
        <ul class="mt-1 ml-5 list-disc">
            @foreach (session('advertencia_stock') as $f)
                <li>{{ $f['producto'] }}: pides {{ $num($f['pedido']) }} y hay {{ $num($f['disponible']) }}.</li>
            @endforeach
        </ul>
        <p class="mt-2">
            Puedes guardar la cotización igual. El stock se descuenta recién cuando se aprueba un recibo o una factura.
        </p>
        <button type="submit" name="confirmar_stock" value="1"
                class="mt-3 rounded-md bg-yellow-600 px-4 py-2 font-semibold text-white hover:bg-yellow-700">
            Continuar de todas formas
        </button>
    </div>
@endif

<p class="text-sm text-gray-600 dark:text-gray-400">
    Los campos marcados con <span class="text-red-600 font-bold">*</span> son obligatorios.
</p>

<div x-data="cotizacionForm(@js($listaClientes), @js($listaProductos), @js($itemsIniciales), @js(old('id_cliente', $c->id_cliente ?? '')))"
     class="space-y-6">

    {{-- Cliente --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Cliente <span class="text-red-600">*</span>
        </label>
        <input type="text" x-model="busqueda" placeholder="Buscar por documento o nombre"
               class="{{ $inputClase }}">
        <select name="id_cliente" x-model="idCliente" required class="{{ $inputClase }}">
            <option value="">-- Seleccione --</option>
            <template x-for="cl in clientesFiltrados" :key="cl.id">
                <option :value="cl.id" :selected="cl.id == idCliente" x-text="cl.texto"></option>
            </template>
        </select>
    </div>

    {{-- Vigencia --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Vigencia @unless ($c)<span class="text-red-600">*</span>@endunless
        </label>
        <div class="flex gap-3 mt-1">
            <input type="number" name="vigencia_cantidad" min="1" max="365"
                   value="{{ old('vigencia_cantidad', $c ? '' : 15) }}"
                   @unless ($c) required @endunless
                   class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            <select name="vigencia_unidad"
                    class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                <option value="DIAS" @selected(old('vigencia_unidad', 'DIAS') === 'DIAS')>Días</option>
                <option value="HORAS" @selected(old('vigencia_unidad') === 'HORAS')>Horas</option>
            </select>
        </div>
        @if ($c)
            <p class="text-xs text-gray-500 mt-1">
                Vence actualmente el {{ $c->fecha_vencimiento->format('d/m/Y H:i') }}.
                Déjelo vacío para mantener esa fecha, o indique una nueva vigencia contada desde ahora.
            </p>
        @else
            <p class="text-xs text-gray-500 mt-1">Se cuenta desde el momento en que guarda la cotización.</p>
        @endif
    </div>

    {{-- Líneas --}}
    <div>
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Productos y servicios @unless ($bloqueoItems)<span class="text-red-600">*</span>@endunless
        </h3>

        @if ($bloqueoItems)
            {{-- Con recibos emitidos, los ítems no se modifican --}}
            <div class="mt-2 rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">
                Esta cotización ya tiene recibos emitidos: sus ítems no se pueden modificar.
                Solo puedes cambiar el cliente, la vigencia y las observaciones.
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm text-gray-700 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                        <tr>
                            <th class="px-3 py-2">Descripción</th>
                            <th class="px-3 py-2 text-right">Cant.</th>
                            <th class="px-3 py-2 text-right">Precio</th>
                            <th class="px-3 py-2 text-right">Desc.</th>
                            <th class="px-3 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($c->detalles as $d)
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-3 py-2">{{ $d->descripcion }}</td>
                                <td class="px-3 py-2 text-right">{{ $num($d->cantidad) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d->precio_unitario, 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d->descuento, 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($d->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t dark:border-gray-700 font-bold">
                            <td colspan="4" class="px-3 py-2 text-right">TOTAL (Bs)</td>
                            <td class="px-3 py-2 text-right">{{ number_format($c->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="space-y-3 mt-2">
                <template x-for="(it, i) in items" :key="i">
                    <div class="border dark:border-gray-700 rounded-md p-3 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                            <div class="md:col-span-5">
                                <label class="block text-xs text-gray-600 dark:text-gray-400">Producto</label>
                                <select :name="'items[' + i + '][id_producto]'" x-model="it.id_producto"
                                        @change="elegirProducto(i)" required class="{{ $inputClase }}">
                                    <option value="">-- Seleccione --</option>
                                    <template x-for="p in productos" :key="p.id">
                                        <option :value="p.id" :selected="p.id == it.id_producto" x-text="p.descripcion"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-600 dark:text-gray-400">
                                    Cantidad <span x-text="producto(it.id_producto) ? '(' + producto(it.id_producto).unidad + ')' : ''"></span>
                                </label>
                                <input type="number" step="0.01" min="0.01" :name="'items[' + i + '][cantidad]'"
                                       x-model="it.cantidad" required class="{{ $inputClase }}">
                                <template x-if="stockDe(it.id_producto) !== null">
                                    <p class="mt-1 text-xs"
                                       :class="sinStock(it) ? 'font-semibold text-yellow-700' : 'text-gray-500'"
                                       x-text="sinStock(it) ? 'Stock insuficiente: hay ' + fmtNum(stockDe(it.id_producto)) : 'Stock: ' + fmtNum(stockDe(it.id_producto))"></p>
                                </template>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-600 dark:text-gray-400">Precio (Bs)</label>
                                <input type="number" step="0.01" min="0" :name="'items[' + i + '][precio_unitario]'"
                                       x-model="it.precio_unitario" required class="{{ $inputClase }}">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs text-gray-600 dark:text-gray-400">Desc.</label>
                                <input type="number" step="0.01" min="0" :name="'items[' + i + '][descuento]'"
                                       x-model="it.descuento" class="{{ $inputClase }}">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-600 dark:text-gray-400">Subtotal</label>
                                <p class="mt-2 font-semibold text-gray-800 dark:text-gray-200" x-text="fmt(subtotal(it))"></p>
                            </div>
                        </div>

                        <div class="flex gap-3 items-end">
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 dark:text-gray-400">Descripción adicional (opcional)</label>
                                <input type="text" maxlength="1000" :name="'items[' + i + '][descripcion_adicional]'"
                                       x-model="it.descripcion_adicional" class="{{ $inputClase }} uppercase">
                            </div>
                            <button type="button" @click="quitar(i)" x-show="items.length > 1"
                                    class="text-sm text-red-600 hover:underline">Quitar</button>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" @click="agregar()"
                    class="mt-3 text-sm text-indigo-600 hover:underline">+ Agregar otra línea</button>

            <p class="mt-4 text-right text-lg font-bold text-gray-800 dark:text-gray-200">
                Total: Bs <span x-text="fmt(total)"></span>
            </p>
        @endif
    </div>

    {{-- Observaciones --}}
    <div>
        <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Observaciones / condiciones
        </label>
        <textarea id="observaciones" name="observaciones" rows="3" class="{{ $inputClase }}">{{ old('observaciones', $c->observaciones ?? '') }}</textarea>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('cotizacionForm', (clientes, productos, items, idCliente) => ({
            clientes, productos, items, idCliente,
            busqueda: '',

            init() {
                // Si la búsqueda deja un solo cliente, se selecciona solo.
                this.$watch('busqueda', () => {
                    if (this.clientesFiltrados.length === 1) {
                        this.idCliente = this.clientesFiltrados[0].id;
                    }
                });
            },

            get clientesFiltrados() {
                const q = this.busqueda.trim().toLowerCase();
                if (!q) return this.clientes;
                return this.clientes.filter(c =>
                    c.id == this.idCliente || c.texto.toLowerCase().includes(q));
            },

            producto(id) {
                return this.productos.find(p => p.id == id);
            },

            // Stock disponible del producto; null si no controla stock o no hay producto
            stockDe(id) {
                const p = this.producto(id);
                return p && p.stock !== null && p.stock !== undefined ? p.stock : null;
            },

            // Cantidad total pedida del mismo producto (suma todas las líneas)
            pedidoDe(id) {
                return this.items
                    .filter(x => x.id_producto == id)
                    .reduce((suma, x) => suma + (parseFloat(x.cantidad) || 0), 0);
            },

            sinStock(it) {
                const disponible = this.stockDe(it.id_producto);
                return disponible !== null && this.pedidoDe(it.id_producto) > disponible;
            },

            agregar() {
                this.items.push({ id_producto: '', cantidad: 1, precio_unitario: '', descuento: 0, descripcion_adicional: '' });
            },

            quitar(i) {
                if (this.items.length > 1) this.items.splice(i, 1);
            },

            elegirProducto(i) {
                const p = this.producto(this.items[i].id_producto);
                if (p) this.items[i].precio_unitario = p.precio;
            },

            subtotal(it) {
                return (parseFloat(it.cantidad) || 0) * (parseFloat(it.precio_unitario) || 0) - (parseFloat(it.descuento) || 0);
            },

            get total() {
                return this.items.reduce((suma, it) => suma + this.subtotal(it), 0);
            },

            fmt(n) {
                return n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            fmtNum(n) {
                return Number(n).toLocaleString('es-BO', { maximumFractionDigits: 2 });
            },
        }));
    });
</script>
