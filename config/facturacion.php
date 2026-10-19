<?php

return [
    // false = aún sin autorización del SIN: no se envía nada y la factura se muestra "sin valor legal".
    'enviar_al_sin' => env('SIN_ENVIAR_FACTURAS', false),
];
