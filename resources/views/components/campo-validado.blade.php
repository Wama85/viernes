@props([
    'name',
    'label',
    'value' => '',
    'type' => 'text',
    'reglas' => '',
    'maxlength' => null,
    'obligatorio' => false,
    'mayusculas' => false,
    'ayuda' => null,
    'contador' => false,
    'placeholder' => null,
    'inputmode' => null,
    'autocomplete' => 'off',
])

@php
    $id = $attributes->get('id', $name);
    $errorServidor = $errors->first($name);
    $clases = 'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 '
        . 'focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm'
        . ($mayusculas ? ' uppercase' : '');
@endphp

<div class="mb-4" data-campo>
    <label for="{{ $id }}" class="block font-medium text-sm text-gray-700 dark:text-gray-300">
        {{ $label }}
        @if ($obligatorio)
            <span class="text-red-600">*</span>
        @endif
    </label>

    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ old($name, $value) }}"
           data-reglas="{{ $reglas }}"
           @if ($mayusculas) data-mayusculas @endif
           @if ($maxlength) maxlength="{{ $maxlength }}" data-max="{{ $maxlength }}" @endif
           @if ($errorServidor) data-error-servidor="{{ $errorServidor }}" @endif
           @if ($placeholder) placeholder="{{ $placeholder }}" @endif
           @if ($inputmode) inputmode="{{ $inputmode }}" @endif
           autocomplete="{{ $autocomplete }}"
           class="{{ $clases }}"
           {{ $attributes->except(['id', 'class']) }}>

    <div class="val-pie">
        <p data-mensaje class="val-msg" hidden></p>
        @if ($ayuda)
            <p class="val-ayuda mt-1">{{ $ayuda }}</p>
        @endif
        @if ($contador && $maxlength)
            <span data-contador class="val-contador">0/{{ $maxlength }}</span>
        @endif
    </div>
</div>
