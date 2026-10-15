@php
    $precios = (object) $items->pluck('subtotal', 'id_cotizacion_detalle')->map(fn ($v) => (float) $v)->all();
    $seleccionados = array_map('strval', old('items', isset($recibo) ? $recibo->detalles->pluck('id_cotizacion_detalle')->all() : []));
    $formaActual = old('forma_pago', $recibo->forma_pago ?? 'EFECTIVO');
    $campo = 'w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6"
      x-data="{
          precios: @js($precios),
          sel: @js($seleccionados),
          get total() { return this.sel.reduce((s, id) => s + Number(this.precios[id] ?? 0), 0); }
      }">
    @csrf
    @isset($recibo) @method('PUT') @endisset

    {{-- Ítems --}}
    <div>
        <div class="flex justify-between items-center mb-2">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Ítems del recibo <span class="text-red-600">*</span>
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

        <p class="mt-3 text-right text-sm text-gray-700 dark:text-gray-300">
            Total del recibo: <span class="text-lg font-bold">Bs <span x-text="total.toFixed(2)"></span></span>
        </p>
    </div>

    {{-- Forma de pago --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Forma de pago <span class="text-red-600">*</span>
            </label>
            <select name="forma_pago" class="{{ $campo }}">
                @foreach (\App\Modules\Recibos\Models\Recibo::FORMAS_PAGO as $forma)
                    <option value="{{ $forma }}" @selected($formaActual === $forma)>{{ $forma }}</option>
                @endforeach
            </select>
            @error('forma_pago') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Referencia (Nº de transferencia, cheque, etc.)
            </label>
            <input type="text" name="referencia_pago" maxlength="100" class="{{ $campo }} uppercase"
                   value="{{ old('referencia_pago', $recibo->referencia_pago ?? '') }}">
            @error('referencia_pago') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
            {{ $boton }}
        </button>
        <a href="{{ $cancelar }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancelar</a>
    </div>
</form>
