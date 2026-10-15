<?php

namespace App\Support;

class MontoEnLetras
{
    private const BASE = [
        '', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE',
        'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS',
        'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const DECENAS = [
        3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA',
        6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
        5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS',
        9 => 'NOVECIENTOS',
    ];

    /** Ejemplo: 1360 => "UN MIL TRESCIENTOS SESENTA 00/100 BOLIVIANOS" */
    public static function convertir(float $monto): string
    {
        $monto = round($monto, 2);
        $entero = (int) floor($monto);
        $centavos = (int) round(($monto - $entero) * 100);

        $texto = $entero === 0 ? 'CERO' : self::numero($entero);

        return sprintf('%s %02d/100 BOLIVIANOS', $texto, $centavos);
    }

    private static function numero(int $n, bool $apocope = false): string
    {
        if ($n >= 1000000) {
            $millones = intdiv($n, 1000000);
            $resto = $n % 1000000;
            $texto = $millones === 1 ? 'UN MILLÓN' : self::numero($millones, true) . ' MILLONES';

            return $resto > 0 ? $texto . ' ' . self::numero($resto, $apocope) : $texto;
        }

        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;
            $texto = self::menorMil($miles, true) . ' MIL';

            return $resto > 0 ? $texto . ' ' . self::menorMil($resto, $apocope) : $texto;
        }

        return self::menorMil($n, $apocope);
    }

    /** $apocope = true cuando el número va delante de MIL o MILLONES (UN, no UNO). */
    private static function menorMil(int $n, bool $apocope): string
    {
        if ($n === 100) {
            return 'CIEN';
        }

        $partes = [];
        $centenas = intdiv($n, 100);
        $resto = $n % 100;

        if ($centenas > 0) {
            $partes[] = self::CENTENAS[$centenas];
        }

        if ($resto > 0) {
            if ($resto < 30) {
                $texto = self::BASE[$resto];
                if (! $apocope && $resto === 1) {
                    $texto = 'UNO';
                }
                if (! $apocope && $resto === 21) {
                    $texto = 'VEINTIUNO';
                }
                $partes[] = $texto;
            } else {
                $decena = intdiv($resto, 10);
                $unidad = $resto % 10;
                $texto = self::DECENAS[$decena];

                if ($unidad > 0) {
                    $palabra = self::BASE[$unidad];
                    if (! $apocope && $unidad === 1) {
                        $palabra = 'UNO';
                    }
                    $texto .= ' Y ' . $palabra;
                }
                $partes[] = $texto;
            }
        }

        return implode(' ', $partes);
    }
}
