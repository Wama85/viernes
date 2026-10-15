@props(['cotizacion'])

@php
    $clases = match ($cotizacion->estado) {
        'APROBADA'  => 'bg-green-100 text-green-800',
        'PENDIENTE' => 'bg-yellow-100 text-yellow-800',
        'FACTURADA' => 'bg-blue-100 text-blue-800',
        default     => 'bg-gray-200 text-gray-700',
    };
@endphp

<span class="px-2 py-1 rounded text-xs font-semibold {{ $clases }}">{{ $cotizacion->estado }}</span>
@if ($cotizacion->estaVencida())
    <span class="px-2 py-1 rounded text-xs font-semibold bg-red-100 text-red-800">VENCIDA</span>
@endif
