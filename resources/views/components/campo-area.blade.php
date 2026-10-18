@props([
    'name',
    'label',
    'value' => '',
    'reglas' => '',
    'maxlength' => null,
    'obligatorio' => false,
    'ayuda' => null,
    'contador' => false,
    'rows' => 3,
    'placeholder' => null,
])

@php
    $id = $attributes->get('id', $name);
    $errorServidor = $errors->first($name);
@endphp

<div class="mb-4" data-campo>
    <label for="{{ $id }}" class="block font-medium text-sm text-gray-700 dark:text-gray-300">
        {{ $label }}
        @if ($obligatorio)
            <span class="text-red-600">*</span>
        @endif
    </label>

    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
              data-reglas="{{ $reglas }}"
              @if ($maxlength) maxlength="{{ $maxlength }}" data-max="{{ $maxlength }}" @endif
              @if ($errorServidor) data-error-servidor="{{ $errorServidor }}" @endif
              @if ($placeholder) placeholder="{{ $placeholder }}" @endif
              class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
              {{ $attributes->except(['id', 'class']) }}>{{ old($name, $value) }}</textarea>

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
