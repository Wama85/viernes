<?php

namespace App\Support;

trait ConPuntoVenta
{
    /** Fija el punto de venta de esta ejecución (opción --pv) y lo devuelve. */
    protected function puntoVenta(): int
    {
        $pv = $this->option('pv');

        if ($pv !== null && $pv !== '') {
            config(['sin.punto_venta' => (int) $pv]);
        }

        return (int) config('sin.punto_venta');
    }
}
