@props(['nombre', 'etiqueta', 'tipo' => 'text', 'valores' => [], 'textoAgregar' => '+ Agregar'])

@php
    $valores = array_values($valores);
    if (empty($valores)) {
        $valores = [''];
    }
@endphp

<div class="mb-4" x-data="{ items: {{ \Illuminate\Support\Js::from($valores) }} }">
    <x-input-label :value="$etiqueta" />

    <template x-for="(item, index) in items" :key="index">
        <div class="flex items-center gap-2 mt-2">
            <input type="{{ $tipo }}" name="{{ $nombre }}[]" x-model="items[index]"
                   class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
            <button type="button" @click="items.splice(index, 1)"
                    class="text-red-600 text-sm hover:underline">Quitar</button>
        </div>
    </template>

    <button type="button" @click="items.push('')"
            class="mt-2 text-sm text-indigo-600 hover:underline">
        {{ $textoAgregar }}
    </button>

    @foreach ($errors->get($nombre . '.*') as $mensajes)
        <x-input-error :messages="$mensajes" class="mt-1" />
    @endforeach
</div>
