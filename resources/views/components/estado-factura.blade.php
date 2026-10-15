@props(['factura'])

@php
    $colores = [
        'PENDIENTE' => 'bg-yellow-100 text-yellow-800',
        'APROBADA'  => 'bg-green-100 text-green-800',
        'ANULADA'   => 'bg-red-100 text-red-800',
    ];
@endphp

<span class="px-2 py-1 rounded text-xs font-semibold {{ $colores[$factura->estado] ?? 'bg-gray-100 text-gray-800' }}">
    {{ $factura->estado }}
</span>
