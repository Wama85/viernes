@php
    $precios = (object) $items->pluck('subtotal', 'id_cotizacion_detalle')->map(fn ($v) => (float) $v)->all();
    $seleccionados = array_map('strval', old('items', []));
    $formaActual = old('forma_pago', 'EFECTIVO');
    $campo = 'w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6"
      x-data="{
          precios: @js($precios),
          sel: @js($seleccionados),
          forma: @js($formaActual),
          descRaw: @js((string) old('descuento_adicional', '')),
          get total() { return this.sel.reduce((s, id) => s + Number(this.precios[id] ?? 0), 0); },
          get conTarjeta() { return ['TARJETA', 'EFECTIVO_TARJETA'].includes(this.forma); },
          r(x) { return Math.round((x + Number.EPSILON) * 100) / 100; },
          alcanzable(t) {
              let b = this.r(t / 1.13);
              for (let c = -3; c <= 3; c++) {
                  let n = this.r(b + c / 100);
                  if (Math.abs(this.r(n + this.r(n * 0.13)) - t) < 0.005) { return true; }
              }
              return false;
          },
          get descuentoPedido() {
              let t = String(this.descRaw).trim().replace(',', '.');
              if (t === '') { return 0; }
              if (t.endsWith('%')) {
                  let p = parseFloat(t.slice(0, -1));
                  return (isNaN(p) || p < 0) ? NaN : Math.round(this.total * p) / 100;
              }
              let v = parseFloat(t);
              return (isNaN(v) || v < 0) ? NaN : Math.round(v * 100) / 100;
          },
          get descuento() {
              let d = this.descuentoPedido;
              if (Number.isNaN(d) || d <= 0 || this.alcanzable(this.r(this.total - d))) { return d; }
              for (let delta of [0.01, -0.01, 0.02, -0.02, 0.03, -0.03, 0.04, -0.04, 0.05, -0.05]) {
                  let c = this.r(d + delta);
                  if (c > 0 && c < this.total && this.alcanzable(this.r(this.total - c))) { return c; }
              }
              return d;
          },
          get ajustado() { return !Number.isNaN(this.descuentoPedido) && this.descuento !== this.descuentoPedido; },
          get descError() {
              if (Number.isNaN(this.descuentoPedido)) { return 'Escribe un monto (5 u 8.99) o un porcentaje (10%).'; }
              if (this.descuentoPedido > 0 && this.descuentoPedido >= this.total) { return 'El descuento no puede ser igual ni mayor al total (no se permite descontar el 100 %).'; }
              return '';
          },
          get final() { return this.total - (Number.isNaN(this.descuento) ? 0 : this.descuento); }
      }">
    @csrf

    @php($nitEstado = $nitEstado ?? null)

    @if ($nitEstado && $nitEstado['estado'] === 'INVALIDO')
        <div class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-900/20 dark:text-amber-200 dark:border-amber-700">
            <p class="font-semibold">El SIN no reconoce el NIT de este cliente ({{ $nitEstado['mensaje'] }}).</p>
            <p class="mt-1">Si el cliente confirma que es su NIT y pide la factura igual, se emite con código de excepción de NIT. Si no, corrige el documento en la ficha del cliente (por ejemplo, si en realidad es un carnet).</p>
            <label class="mt-3 flex items-center gap-2 font-medium">
                <input type="checkbox" name="confirmar_excepcion" value="1" @checked(old('confirmar_excepcion')) class="rounded border-gray-300">
                Confirmo: emitir con excepción de NIT
            </label>
            @error('confirmar_excepcion') <p class="mt-2 text-red-600">{{ $message }}</p> @enderror
        </div>
    @elseif ($nitEstado && in_array($nitEstado['estado'], ['ACTIVO', 'ESPECIAL'], true))
        <p class="text-xs text-green-700 dark:text-green-400">NIT verificado en el SIN: {{ $nitEstado['mensaje'] }}</p>
    @endif

    <div>
        <div class="flex justify-between items-center mb-2">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Ítems de la factura <span class="text-red-600">*</span>
            </p>
            <div class="text-xs space-x-3">
                <button type="button" class="text-indigo-600 hover:underline" @click="sel = Object.keys(precios)">Seleccionar todos</button>
                <button type="button" class="text-gray-500 hover:underline" @click="sel = []">Quitar todos</button>
            </div>
        </div>

        @error('items') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror

        <div class="divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-md">
            @foreach ($items as $item)
                <label class="flex items-center gap-3 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                    <input type="checkbox" name="items[]" value="{{ $item->id_cotizacion_detalle }}"
                           x-model="sel" class="rounded border-gray-300 dark:bg-gray-900">
                    <span class="flex-1">
                        {{ rtrim(rtrim(number_format($item->cantidad, 2), '0'), '.') }} × {{ $item->descripcion }}
                        @if ($item->descripcion_adicional)
                            <span class="block text-xs text-gray-500">{{ $item->descripcion_adicional }}</span>
                        @endif
                    </span>
                    <span class="font-medium">Bs {{ number_format($item->subtotal, 2) }}</span>
                </label>
            @endforeach
        </div>

        <div class="mt-3 text-right text-sm text-gray-700 dark:text-gray-300 space-y-1">
            <p x-show="descuento > 0 && !descError" style="display: none;">
                Subtotal Bs <span x-text="total.toFixed(2)"></span>
                · Descuento adicional Bs <span x-text="descuento.toFixed(2)"></span>
            </p>
            <p>
                Total de la factura: <span class="text-lg font-bold">Bs <span x-text="final.toFixed(2)"></span></span>
            </p>
        </div>
    </div>

    <div class="max-w-xs">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Forma de pago <span class="text-red-600">*</span>
        </label>
        <select name="forma_pago" x-model="forma" class="{{ $campo }}">
            @foreach (\App\Modules\Facturas\Models\Factura::FORMAS_PAGO as $forma)
                <option value="{{ $forma }}" @selected($formaActual === $forma)>{{ \App\Modules\Facturas\Models\Factura::ETIQUETAS_FORMULARIO[$forma] ?? \App\Modules\Facturas\Models\Factura::ETIQUETAS_PAGO[$forma] }}</option>
            @endforeach
        </select>
        @error('forma_pago') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div x-show="conTarjeta" style="display: none;" class="max-w-xs">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Tarjeta <span class="text-red-600">*</span>
            <span class="block text-xs font-normal text-gray-500">Según el voucher: los 4 primeros y los 4 últimos dígitos. El SIN recibe ceros en el medio.</span>
        </p>
        <div class="flex items-center gap-2">
            <input type="text" name="tarjeta_inicio" value="{{ old('tarjeta_inicio') }}" maxlength="4" inputmode="numeric"
                   placeholder="1234" class="{{ $campo }} w-24 text-center">
            <span class="text-gray-500 text-sm">0000 0000</span>
            <input type="text" name="tarjeta_fin" value="{{ old('tarjeta_fin') }}" maxlength="4" inputmode="numeric"
                   placeholder="5678" class="{{ $campo }} w-24 text-center">
        </div>
        @error('tarjeta_inicio') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        @error('tarjeta_fin') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="max-w-xs">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descuento adicional (opcional)</label>
        <input type="text" name="descuento_adicional" x-model="descRaw" maxlength="12" autocomplete="off"
               placeholder="Ej. 5, 8.99 o 10%" class="{{ $campo }}">
        <p x-show="descError" x-text="descError" class="text-sm text-red-600 mt-1" style="display: none;"></p>
        <p x-show="ajustado && !descError" class="text-xs text-amber-700 mt-1" style="display: none;">
            Se ajusta a Bs <span x-text="descuento.toFixed(2)"></span> para que el total se pueda facturar al centavo en el SIN.
        </p>
        @error('descuento_adicional') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                :disabled="sel.length === 0 || descError !== ''" :class="(sel.length === 0 || descError !== '') ? 'opacity-50 cursor-not-allowed' : ''">
            {{ $boton }}
        </button>
        <a href="{{ $cancelar }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancelar</a>
    </div>
</form>
