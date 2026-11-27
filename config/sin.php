<?php

return [
    'ambiente'       => (int) env('SIN_AMBIENTE', 2),
    'modalidad'      => (int) env('SIN_MODALIDAD', 2),
    'nit'            => env('SIN_NIT'),
    'codigo_sistema' => env('SIN_CODIGO_SISTEMA'),
    'sucursal'       => (int) env('SIN_SUCURSAL', 0),
    'punto_venta'    => (int) env('SIN_PUNTO_VENTA', 0),
    'token'          => env('SIN_TOKEN'),
    'url_base'       => rtrim(env('SIN_URL_BASE', 'https://pilotosiatservicios.impuestos.gob.bo/sfd'), '/'),

    // Formato del XML de la factura: 'v2' (precios con IVA incluido) o 'v3' (precios netos, IVA aparte).
    // Vacío: se deduce de la URL (…/v2 => v2; …/sfd => v3).
    'formato'        => env('SIN_FORMATO'),

    // Datos del emisor que van dentro de la factura
    'razon_social'   => env('SIN_RAZON_SOCIAL'),
    'municipio'      => env('SIN_MUNICIPIO', 'COCHABAMBA'),
    'telefono'       => env('SIN_TELEFONO') ?: null,

    // Actividades económicas vigentes de la empresa (las que se ofrecen al crear productos del SIN).
    'actividades_propias' => ['9511000', '4741100', '6311000', '6202000'],
];
