@php
    $c = $cotizacion ?? null;
    $bloqueoItems = $c && $c->tieneRecibos();
    $inputClase = 'block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm';
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

    $lineaVacia = ['id_producto' => '', 'cantidad' => '1', 'precio_unitario' => '', 'descuento' => '0', 'descripcion_adicional' => ''];

    if (old('items')) {
        $itemsIniciales = collect(old('items'))->values()->map(fn ($i) => [
            'id_producto'           => $i['id_producto'] ?? '',
            'cantidad'              => $i['cantidad'] ?? '1',
            'precio_unitario'       => $i['precio_unitario'] ?? '',
            'descuento'             => $i['descuento'] ?? '0',
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

    $descuentoGeneralInicial = old('descuento_general', ($c && (float) $c->descuento_general > 0) ? $num($c->descuento_general) : '');

    // Errores del servidor por línea: 'items.0.cantidad' pasa a '0.cantidad'.
    $erroresItems = collect($errors->getMessages())
        ->filter(fn ($m, $k) => str_starts_with($k, 'items.'))
        ->map(fn ($m) => $m[0])
        ->mapWithKeys(fn ($m, $k) => [substr($k, 6) => $m])
        ->all();
@endphp

<div data-resumen class="val-resumen" hidden></div>

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

<div x-data="cotizacionForm(@js($listaClientes), @js($listaProductos), @js($itemsIniciales), @js(old('id_cliente', $c->id_cliente ?? '')), @js($erroresItems), @js($descuentoGeneralInicial))"
     class="space-y-6">

    {{-- Cliente --}}
    <div data-campo>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Cliente <span class="text-red-600">*</span>
        </label>
        <input type="text" x-model="busqueda" placeholder="Buscar por documento o nombre"
               autocomplete="off" class="{{ $inputClase }}">
        <select name="id_cliente" x-model="idCliente" data-reglas="requerido"
                @if ($errors->first('id_cliente')) data-error-servidor="{{ $errors->first('id_cliente') }}" @endif
                class="{{ $inputClase }}">
            <option value="">-- Seleccione --</option>
            <template x-for="cl in clientesFiltrados" :key="cl.id">
                <option :value="cl.id" :selected="cl.id == idCliente" x-text="cl.texto"></option>
            </template>
        </select>
        <div class="val-pie"><p data-mensaje class="val-msg" hidden></p></div>
    </div>

    {{-- Vigencia --}}
    <div data-campo>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Vigencia @unless ($c)<span class="text-red-600">*</span>@endunless
        </label>
        <div class="flex gap-3 mt-1">
            <input type="text" name="vigencia_cantidad" inputmode="numeric" maxlength="3" autocomplete="off"
                   value="{{ old('vigencia_cantidad', $c ? '' : 15) }}"
                   data-reglas="{{ $c ? 'entero:365' : 'requerido|entero:365' }}"
                   @if ($errors->first('vigencia_cantidad')) data-error-servidor="{{ $errors->first('vigencia_cantidad') }}" @endif
                   class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
            <select name="vigencia_unidad"
                    class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                <option value="DIAS" @selected(old('vigencia_unidad', 'DIAS') === 'DIAS')>Días</option>
                <option value="HORAS" @selected(old('vigencia_unidad') === 'HORAS')>Horas</option>
            </select>
        </div>
        <div class="val-pie"><p data-mensaje class="val-msg" hidden></p></div>
        @if ($c)
            <p class="text-xs text-gray-500 mt-1">
                Vence actualmente el {{ $c->fecha_vencimiento->format('d/m/Y H:i') }}.
                Déjelo vacío para mantener esa fecha, o indique una nueva vigencia contada desde ahora.
            </p>
        @else
            <p class="text-xs text-gray-500 mt-1">Se cuenta desde el momento en que guarda la cotización (de 1 a 365).</p>
        @endif
    </div>

    {{-- Líneas --}}
    <div>
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Productos y servicios @unless ($bloqueoItems)<span class="text-red-600">*</span>@endunless
        </h3>

        @if ($errors->first('items'))
            <p class="mt-1 text-sm text-red-600">{{ $errors->first('items') }}</p>
        @endif

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
                        @if ((float) $c->descuento_general > 0)
                            <tr class="border-t dark:border-gray-700">
                                <td colspan="4" class="px-3 py-2 text-right">Subtotal (Bs)</td>
                                <td class="px-3 py-2 text-right">{{ number_format($c->subtotalItems(), 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="px-3 py-2 text-right">Descuento general (Bs)</td>
                                <td class="px-3 py-2 text-right">-{{ number_format($c->descuento_general, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="border-t dark:border-gray-700 font-bold">
                            <td colspan="4" class="px-3 py-2 text-right">TOTAL (Bs)</td>
                            <td class="px-3 py-2 text-right">{{ number_format($c->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            {{-- Atajo: copia el mismo descuento en todas las líneas (luego se puede ajustar una por una) --}}
            <div class="mt-2 flex flex-wrap items-start gap-2 text-sm">
                <label class="mt-2 text-gray-600 dark:text-gray-400">Mismo descuento para todas las líneas:</label>
                <div>
                    <input type="text" inputmode="decimal" maxlength="10" autocomplete="off"
                           x-model="descGlobal" placeholder="10% · 12.50"
                           @keydown.enter.prevent="aplicarATodas()"
                           class="w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                    <p x-show="descGlobalError" x-text="descGlobalError" class="mt-1 text-xs text-red-600"></p>
                </div>
                <button type="button" @click="aplicarATodas()"
                        class="mt-1 rounded-md border border-indigo-600 px-3 py-1 text-indigo-600 hover:bg-indigo-50">
                    Aplicar a todas
                </button>
            </div>

            <div class="space-y-3 mt-2">
                <template x-for="(it, i) in items" :key="it.uid">
                    <div class="border dark:border-gray-700 rounded-md p-3 space-y-3" data-linea
                         x-init="$nextTick(() => window.Validacion && window.Validacion.revisarServidor($el))">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">

                            {{-- Producto --}}
                            <div class="md:col-span-4" data-campo>
                                <label class="block text-xs text-gray-600 dark:text-gray-400">
                                    Producto <span class="text-red-600">*</span>
                                </label>
                                <select :name="'items[' + i + '][id_producto]'" x-model="it.id_producto"
                                        @change="elegirProducto(i)"
                                        data-reglas="requerido" data-f="producto"
                                        :data-error-servidor="errores[i + '.id_producto'] || ''"
                                        class="{{ $inputClase }}">
                                    <option value="">-- Seleccione --</option>
                                    <template x-for="p in productos" :key="p.id">
                                        <option :value="p.id" :selected="p.id == it.id_producto" x-text="p.descripcion"></option>
                                    </template>
                                </select>
                                <p data-mensaje class="val-msg" hidden></p>
                            </div>

                            {{-- Cantidad --}}
                            <div class="md:col-span-2" data-campo>
                                <label class="block text-xs text-gray-600 dark:text-gray-400">
                                    Cantidad <span x-text="producto(it.id_producto) ? '(' + producto(it.id_producto).unidad + ')' : ''"></span>
                                    <span class="text-red-600">*</span>
                                </label>
                                <input type="text" inputmode="decimal" maxlength="8" autocomplete="off"
                                       :name="'items[' + i + '][cantidad]'" x-model="it.cantidad"
                                       data-reglas="requerido|decimal:99999.99|positivo" data-f="cantidad"
                                       :data-error-servidor="errores[i + '.cantidad'] || ''"
                                       class="{{ $inputClase }}">
                                <template x-if="stockDe(it.id_producto) !== null">
                                    <p class="mt-1 text-xs"
                                       :class="sinStock(it) ? 'font-semibold text-yellow-700' : 'text-gray-500'"
                                       x-text="sinStock(it) ? 'Stock insuficiente: hay ' + fmtNum(stockDe(it.id_producto)) : 'Stock: ' + fmtNum(stockDe(it.id_producto))"></p>
                                </template>
                                <p data-mensaje class="val-msg" hidden></p>
                            </div>

                            {{-- Precio --}}
                            <div class="md:col-span-2" data-campo>
                                <label class="block text-xs text-gray-600 dark:text-gray-400">
                                    Precio (Bs) <span class="text-red-600">*</span>
                                </label>
                                <input type="text" inputmode="decimal" maxlength="10" autocomplete="off"
                                       :name="'items[' + i + '][precio_unitario]'" x-model="it.precio_unitario"
                                       data-reglas="requerido|decimal:9999999.99" data-f="precio"
                                       :data-error-servidor="errores[i + '.precio_unitario'] || ''"
                                       class="{{ $inputClase }}">
                                <p data-mensaje class="val-msg" hidden></p>
                            </div>

                            {{-- Descuento --}}
                            <div class="md:col-span-2" data-campo>
                                <label class="block text-xs text-gray-600 dark:text-gray-400">Descuento (Bs o %)</label>
                                <input type="text" inputmode="decimal" maxlength="10" autocomplete="off"
                                       placeholder="0 · 12.50 · 10%"
                                       :name="'items[' + i + '][descuento]'" x-model="it.descuento"
                                       data-reglas="descuentoLinea" data-f="descuento"
                                       :data-error-servidor="errores[i + '.descuento'] || ''"
                                       class="{{ $inputClase }}">
                                <p data-mensaje class="val-msg" hidden></p>
                            </div>

                            {{-- Subtotal --}}
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-600 dark:text-gray-400">Subtotal</label>
                                <p class="mt-2 font-semibold text-gray-800 dark:text-gray-200" x-text="fmt(subtotal(it))"></p>
                            </div>
                        </div>

                        <div class="flex gap-3 items-start">
                            <div class="flex-1" data-campo>
                                <label class="block text-xs text-gray-600 dark:text-gray-400">Descripción adicional (opcional)</label>
                                <input type="text" maxlength="300" autocomplete="off"
                                       :name="'items[' + i + '][descripcion_adicional]'" x-model="it.descripcion_adicional"
                                       data-reglas="texto|max:300" data-f="adicional"
                                       :data-error-servidor="errores[i + '.descripcion_adicional'] || ''"
                                       class="{{ $inputClase }} uppercase">
                                <div class="val-pie">
                                    <p data-mensaje class="val-msg" hidden></p>
                                    <span class="val-contador" x-text="(it.descripcion_adicional || '').length + '/300'"></span>
                                </div>
                            </div>
                            <button type="button" @click="quitar(i)" x-show="items.length > 1"
                                    class="mt-6 text-sm text-red-600 hover:underline">Quitar</button>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" @click="agregar()" x-show="items.length < 100"
                    class="mt-3 text-sm text-indigo-600 hover:underline">+ Agregar otra línea</button>

            {{-- Descuento sobre el total: pasa a la factura, repartido entre los ítems que se facturen --}}
            <div class="mt-4 flex flex-wrap items-start justify-end gap-3 text-sm" data-campo>
                <label for="descuento_general" class="mt-2 text-gray-600 dark:text-gray-400">Descuento general (Bs o %):</label>
                <div>
                    <input id="descuento_general" type="text" name="descuento_general" inputmode="decimal" maxlength="10" autocomplete="off"
                           x-model="descGeneral" placeholder="0 · 50 · 10%"
                           data-reglas="descuentoLinea" data-f="descuentoGeneral"
                           @if ($errors->first('descuento_general')) data-error-servidor="{{ $errors->first('descuento_general') }}" @endif
                           class="w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                    <p data-mensaje class="val-msg" hidden></p>
                </div>
            </div>
            <p x-show="descuentoGeneralBs() > 0" x-cloak class="mt-1 text-right text-sm text-gray-600 dark:text-gray-400">
                Subtotal: Bs <span x-text="fmt(subtotalItems)"></span>
                · Descuento general: -Bs <span x-text="fmt(descuentoGeneralBs())"></span>
            </p>

            <p class="mt-2 text-right text-lg font-bold text-gray-800 dark:text-gray-200">
                Total: Bs <span x-text="fmt(total)"></span>
            </p>
        @endif
    </div>

    {{-- Observaciones --}}
    <x-campo-area name="observaciones" label="Observaciones / condiciones"
                  :value="$c->observaciones ?? ''"
                  :maxlength="1000" :contador="true" :rows="3"
                  reglas="texto|max:1000" />
</div>

<script>
    /* Reglas propias de este formulario. Se registran cuando el motor de validación ya está cargado. */
    (function () {
        function registrar() {
            if (!window.Validacion) return false;

            Object.assign(window.Validacion.reglas, {
                entero: function (v, max) {
                    var t = v.trim();
                    if (!/^\d+$/.test(t)) return 'Escribe un número entero.';
                    if (+t < 1) return 'El mínimo es 1.';
                    if (+t > +max) return 'El máximo es ' + max + '.';
                    return true;
                },

                // Descuento de línea: monto en Bs («12.50») o porcentaje («10%»).
                descuentoLinea: function (v, _a, campo) {
                    var t = String(v).replace(/\s+/g, '').replace(',', '.');
                    if (t === '') return true;

                    if (!/^\d+(\.\d{1,2})?%?$/.test(t)) {
                        return 'Escribe un monto en Bs (por ejemplo 12.50) o un porcentaje (por ejemplo 10%).';
                    }

                    var esPorcentaje = t.slice(-1) === '%';
                    var numero = parseFloat(t);

                    if (esPorcentaje) {
                        return numero <= 100 || 'El porcentaje de descuento no puede superar 100%.';
                    }
                    if (numero > 9999999.99) return 'El descuento máximo es 9,999,999.99.';

                    var linea = campo.closest('[data-linea]');
                    if (!linea) return true;

                    var leer = function (sel) {
                        var el = linea.querySelector(sel);
                        return el ? parseFloat(String(el.value).replace(',', '.')) : NaN;
                    };
                    var cantidad = leer('[data-f="cantidad"]');
                    var precio = leer('[data-f="precio"]');
                    if (isNaN(cantidad) || isNaN(precio)) return true;

                    var bruto = Math.round(cantidad * precio * 100) / 100;
                    return numero <= bruto
                        || 'El descuento no puede ser mayor al importe de la línea (Bs ' + bruto.toFixed(2) + ').';
                }
            });

            return true;
        }

        if (!registrar()) document.addEventListener('DOMContentLoaded', registrar);
    })();

    document.addEventListener('alpine:init', () => {
        Alpine.data('cotizacionForm', (clientes, productos, items, idCliente, errores, descuentoGeneral) => ({
            clientes, productos, idCliente, errores,
            descGeneral: descuentoGeneral || '',
            busqueda: '',
            descGlobal: '',
            descGlobalError: '',
            items: items.map((it, n) => ({ ...it, uid: n })),
            siguiente: items.length,

            init() {
                // Si la búsqueda deja un solo cliente, se selecciona solo.
                this.$watch('busqueda', () => {
                    if (this.clientesFiltrados.length === 1) {
                        this.idCliente = this.clientesFiltrados[0].id;
                    }
                });

                // Al cambiar el cliente (por búsqueda o por selección), se revalida el campo.
                this.$watch('idCliente', () => {
                    this.$nextTick(() => {
                        const select = this.$el.querySelector('select[name="id_cliente"]');
                        if (select && window.Validacion) window.Validacion.revisarCampo(select, true);
                    });
                });

                // Al cambiar cantidad o precio, se revalida el descuento de esa línea.
                this.$el.addEventListener('validacion:cambio', (e) => this.dependientes(e.target));
            },

            get clientesFiltrados() {
                const q = this.busqueda.trim().toLowerCase();
                if (!q) return this.clientes;
                return this.clientes.filter(c =>
                    c.id == this.idCliente || c.texto.toLowerCase().includes(q));
            },

            num(v) {
                return parseFloat(String(v ?? '').replace(',', '.')) || 0;
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
                    .reduce((suma, x) => suma + this.num(x.cantidad), 0);
            },

            sinStock(it) {
                const disponible = this.stockDe(it.id_producto);
                return disponible !== null && this.pedidoDe(it.id_producto) > disponible;
            },

            agregar() {
                this.items.push({
                    uid: this.siguiente++,
                    id_producto: '', cantidad: '1', precio_unitario: '', descuento: '0', descripcion_adicional: '',
                });
                this.$nextTick(() => {
                    const lineas = this.$el.querySelectorAll('[data-linea]');
                    const ultima = lineas[lineas.length - 1];
                    const select = ultima && ultima.querySelector('select');
                    if (select) select.focus();
                });
            },

            quitar(i) {
                if (this.items.length > 1) this.items.splice(i, 1);
            },

            elegirProducto(i) {
                const p = this.producto(this.items[i].id_producto);
                if (p) this.items[i].precio_unitario = p.precio;

                // El precio cambió solo: se revalida junto con el descuento de la línea.
                this.$nextTick(() => {
                    const linea = this.$el.querySelectorAll('[data-linea]')[i];
                    if (!linea || !window.Validacion) return;
                    linea.querySelectorAll('[data-f="precio"], [data-f="descuento"]')
                        .forEach((el) => window.Validacion.revisarCampo(el, true));
                });
            },

            dependientes(campo) {
                const f = campo && campo.dataset ? campo.dataset.f : null;
                if (f !== 'cantidad' && f !== 'precio') return;

                const linea = campo.closest('[data-linea]');
                const descuento = linea && linea.querySelector('[data-f="descuento"]');
                if (descuento && window.Validacion) window.Validacion.revisarCampo(descuento, true);
            },

            // Copia el descuento escrito arriba en todas las líneas y las revalida.
            aplicarATodas() {
                const t = String(this.descGlobal).replace(/\s+/g, '').replace(',', '.');
                this.descGlobalError = '';

                if (!/^\d+(\.\d{1,2})?%?$/.test(t)) {
                    this.descGlobalError = 'Escribe un monto (12.50) o un porcentaje (10%).';
                    return;
                }
                if (t.endsWith('%') && parseFloat(t) > 100) {
                    this.descGlobalError = 'El porcentaje no puede superar 100%.';
                    return;
                }

                this.items.forEach((it) => { it.descuento = t; });

                this.$nextTick(() => {
                    if (!window.Validacion) return;
                    this.$el.querySelectorAll('[data-f="descuento"]')
                        .forEach((el) => window.Validacion.revisarCampo(el, true));
                });
            },

            // Descuento de la línea en Bs: monto directo o porcentaje del importe (cantidad × precio).
            descuentoDe(it) {
                const t = String(it.descuento ?? '').replace(/\s+/g, '').replace(',', '.');
                if (t === '') return 0;

                if (t.endsWith('%')) {
                    const p = parseFloat(t);
                    const bruto = Math.round(this.num(it.cantidad) * this.num(it.precio_unitario) * 100) / 100;
                    return isNaN(p) ? 0 : Math.round(bruto * p) / 100;
                }
                return this.num(t);
            },

            subtotal(it) {
                return this.num(it.cantidad) * this.num(it.precio_unitario) - this.descuentoDe(it);
            },

            get subtotalItems() {
                return this.items.reduce((suma, it) => suma + this.subtotal(it), 0);
            },

            // Descuento general en Bs: monto directo o porcentaje de la suma de ítems.
            descuentoGeneralBs() {
                const t = String(this.descGeneral ?? '').replace(/\s+/g, '').replace(',', '.');
                if (t === '') return 0;

                if (t.endsWith('%')) {
                    const p = parseFloat(t);
                    return isNaN(p) ? 0 : Math.round(this.subtotalItems * p) / 100;
                }
                return this.num(t);
            },

            get total() {
                return this.subtotalItems - this.descuentoGeneralBs();
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
