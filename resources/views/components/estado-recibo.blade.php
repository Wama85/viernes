@props(['recibo'])

@php
    $clases = match ($recibo->estado) {
        'APROBADO'  => 'bg-green-100 text-green-800',
        'PENDIENTE' => 'bg-yellow-100 text-yellow-800',
        default     => 'bg-gray-200 text-gray-700',
    };
@endphp

<span class="px-2 py-1 rounded text-xs font-semibold {{ $clases }}">{{ $recibo->estado }}</span>
