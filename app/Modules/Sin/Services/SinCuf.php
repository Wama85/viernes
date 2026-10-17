<?php

namespace App\Modules\Sin\Services;

use Carbon\CarbonInterface;

class SinCuf
{
    /**
     * Cadena de 53 dígitos + dígito verificador (módulo 11),
     * convertida a hexadecimal y seguida del código de control del CUFD.
     */
    public static function calcular(
        string|int $nit,
        CarbonInterface $fecha,
        int $sucursal,
        int $modalidad,
        int $tipoEmision,
        int $tipoFactura,
        int $documentoSector,
        int $numero,
        int $puntoVenta,
        string $codigoControl
    ): string {
        $cadena = self::rellenar($nit, 13)
            . $fecha->format('YmdHisv')
            . self::rellenar($sucursal, 4)
            . $modalidad
            . $tipoEmision
            . $tipoFactura
            . self::rellenar($documentoSector, 2)
            . self::rellenar($numero, 10)
            . self::rellenar($puntoVenta, 4);

        $cadena .= self::modulo11($cadena);

        return self::aHexadecimal($cadena) . $codigoControl;
    }

    private static function rellenar(string|int $valor, int $largo): string
    {
        return str_pad((string) $valor, $largo, '0', STR_PAD_LEFT);
    }

    private static function modulo11(string $cadena): string
    {
        $suma = 0;
        $multiplicador = 2;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += (int) $cadena[$i] * $multiplicador;
            $multiplicador = $multiplicador >= 9 ? 2 : $multiplicador + 1;
        }

        $digito = $suma % 11;

        if ($digito === 10) {
            return '1';
        }

        return (string) $digito;
    }

    private static function aHexadecimal(string $decimal): string
    {
        $hex = '';
        $n = ltrim($decimal, '0');

        while (bccomp($n, '0') > 0) {
            $hex = dechex((int) bcmod($n, '16')) . $hex;
            $n = bcdiv($n, '16', 0);
        }

        return strtoupper($hex);
    }
}
