@props([
    'name' => 'password',
    'label',
    'reglas' => '',
    'obligatorio' => false,
    'ayuda' => null,
    'medidor' => false,
    'autocomplete' => 'new-password',
])

@php
    $id = $attributes->get('id', $name);
    $errorServidor = $errors->first($name);
@endphp

<div class="mb-4" data-campo
     x-data="{
         ver: false,
         valor: '',
         get puntos() {
             const v = this.valor;
             if (!v) return 0;
             let p = 0;
             if (v.length >= 8) p++;
             if (/\p{L}/u.test(v) && /\d/.test(v)) p++;
             if (v.length >= 12) p++;
             if (/\p{Lu}/u.test(v) && /\p{Ll}/u.test(v)) p++;
             if (/[^\p{L}\d]/u.test(v)) p++;
             return Math.max(1, Math.min(p, 4));
         },
         get etiqueta() { return ['', 'Débil', 'Regular', 'Buena', 'Fuerte'][this.puntos]; }
     }">

    <label for="{{ $id }}" class="block font-medium text-sm text-gray-700 dark:text-gray-300">
        {{ $label }}
        @if ($obligatorio)
            <span class="text-red-600">*</span>
        @endif
    </label>

    <div class="val-clave">
        <input id="{{ $id }}" name="{{ $name }}" type="password" :type="ver ? 'text' : 'password'"
               x-model="valor" maxlength="72" data-max="72"
               data-reglas="{{ $reglas }}"
               @if ($errorServidor) data-error-servidor="{{ $errorServidor }}" @endif
               autocomplete="{{ $autocomplete }}"
               class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">

        <button type="button" class="val-ojo" tabindex="-1"
                @click="ver = !ver" :aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'">
            <svg x-show="!ver" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <svg x-show="ver" x-cloak fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
        </button>
    </div>

    @if ($medidor)
        <div class="val-fuerza" x-show="valor.length" x-cloak>
            <div class="val-barra">
                <span :class="puntos >= 1 ? 'n' + puntos : ''"></span>
                <span :class="puntos >= 2 ? 'n' + puntos : ''"></span>
                <span :class="puntos >= 3 ? 'n' + puntos : ''"></span>
                <span :class="puntos >= 4 ? 'n' + puntos : ''"></span>
            </div>
            <small x-text="etiqueta"></small>
        </div>
    @endif

    <div class="val-pie">
        <p data-mensaje class="val-msg" hidden></p>
        @if ($ayuda)
            <p class="val-ayuda mt-1">{{ $ayuda }}</p>
        @endif
    </div>
</div>
