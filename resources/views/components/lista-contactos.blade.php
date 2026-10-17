@props([
    'nombre',
    'etiqueta',
    'tipo' => 'text',
    'valores' => [],
    'textoAgregar' => '+ Agregar',
    'reglas' => '',
    'max' => 3,
    'maxlength' => 100,
    'placeholder' => '',
])

@php
    $lista = collect($valores)->values();
    if ($lista->isEmpty()) {
        $lista = collect(['']);
    }

    $filas = $lista->map(fn ($valor, $i) => [
        'id' => $i,
        'valor' => (string) $valor,
        'error' => (string) $errors->first($nombre . '.' . $i),
    ])->all();

    $errorLista = $errors->first($nombre);
@endphp

<div class="mb-4"
     x-data="{
         filas: @js($filas),
         siguiente: {{ count($filas) }},
         max: {{ (int) $max }},
         agregar() {
             if (this.filas.length >= this.max) return;
             this.filas.push({ id: this.siguiente++, valor: '', error: '' });
             this.$nextTick(() => {
                 const campos = this.$root.querySelectorAll('input');
                 if (campos.length) campos[campos.length - 1].focus();
             });
         },
         quitar(i) {
             this.filas.splice(i, 1);
             if (this.filas.length === 0) this.filas.push({ id: this.siguiente++, valor: '', error: '' });
             this.$nextTick(() => window.Validacion && window.Validacion.revalidarGrupo(this.$root.closest('form'), '{{ $nombre }}'));
         }
     }">

    <span class="block font-medium text-sm text-gray-700 dark:text-gray-300">{{ $etiqueta }}</span>

    <template x-for="(fila, i) in filas" :key="fila.id">
        <div class="mt-1" data-campo
             x-init="$nextTick(() => window.Validacion && window.Validacion.revisarServidor($el))">
            <div class="flex items-center gap-2">
                <input type="{{ $tipo }}" name="{{ $nombre }}[]" x-model="fila.valor"
                       maxlength="{{ $maxlength }}"
                       data-reglas="{{ $reglas }}" data-grupo="{{ $nombre }}"
                       :data-error-servidor="fila.error"
                       placeholder="{{ $placeholder }}" autocomplete="off"
                       class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">

                <button type="button" @click="quitar(i)" x-show="filas.length > 1 || fila.valor !== ''"
                        class="shrink-0 rounded-md p-2 text-gray-400 hover:text-red-600" aria-label="Quitar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p data-mensaje class="val-msg" hidden></p>
        </div>
    </template>

    @if ($errorLista)
        <p class="mt-1 text-sm text-red-600">{{ $errorLista }}</p>
    @endif

    <div class="mt-2 flex items-center gap-3">
        <button type="button" @click="agregar()" x-show="filas.length < max"
                class="text-sm text-indigo-600 hover:underline">{{ $textoAgregar }}</button>
        <span x-show="filas.length >= max" x-cloak class="val-ayuda">Máximo {{ (int) $max }}.</span>
    </div>
</div>
