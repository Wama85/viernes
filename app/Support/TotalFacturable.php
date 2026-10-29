<?php

namespace App\Support;

/**
 * La v3 del SIN exige IVA = 13 % exacto del neto, y el neto va en centavos. Por eso algunos totales
 * (p. ej. 100,00) no se pueden facturar al centavo. Esta clase los detecta y propone enteros cercanos.
 */
class TotalFacturable
{
    /** Total que resulta de un neto: neto + 13 % redondeado. */
    private static function totalDeNeto(float $neto): float
    {
        return round($neto + round($neto * 0.13, 2), 2);
    }

    /** ¿Existe un neto en centavos cuyo total con IVA sea exactamente este? */
    public static function esAlcanzable(float $total): bool
    {
        $base = round($total / 1.13, 2);

        for ($c = -3; $c <= 3; $c++) {
            if (abs(self::totalDeNeto(round($base + $c / 100, 2)) - $total) < 0.005) {
                return true;
            }
        }

        return false;
    }

    /**
     * Totales ENTEROS (en pesos) alcanzables más cercanos al total dado: [menor, mayor].
     * Alguno puede ser null.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function enterosCercanos(float $total): array
    {
        $menor = null;
        $mayor = null;
        $piso = (int) floor($total);
        $techo = (int) ceil($total);

        for ($i = 0; $i <= 50; $i++) {
            $abajo = $piso - $i;
            $arriba = $techo + $i;

            if ($menor === null && $abajo > 0 && $abajo < $total && self::esAlcanzable((float) $abajo)) {
                $menor = $abajo;
            }

            if ($mayor === null && $arriba > $total && self::esAlcanzable((float) $arriba)) {
                $mayor = $arriba;
            }
        }

        return [$menor, $mayor];
    }
}
