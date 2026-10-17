<?php

namespace App\Modules\Sin\Services;

class SinTar
{
    /** @param array<string, string> $archivos nombre => contenido */
    public static function crear(array $archivos): string
    {
        $tar = '';

        foreach ($archivos as $nombre => $contenido) {
            $tar .= self::cabecera($nombre, strlen($contenido));
            $tar .= $contenido;

            $resto = strlen($contenido) % 512;

            if ($resto > 0) {
                $tar .= str_repeat("\0", 512 - $resto);
            }
        }

        return $tar . str_repeat("\0", 1024);
    }

    private static function cabecera(string $nombre, int $tamano): string
    {
        $h  = str_pad($nombre, 100, "\0");
        $h .= sprintf('%07o', 0644) . "\0";
        $h .= sprintf('%07o', 0) . "\0";
        $h .= sprintf('%07o', 0) . "\0";
        $h .= sprintf('%011o', $tamano) . "\0";
        $h .= sprintf('%011o', time()) . "\0";
        $h .= '        ';
        $h .= '0';
        $h .= str_repeat("\0", 100);
        $h .= "ustar\0" . '00';
        $h .= str_repeat("\0", 32);
        $h .= str_repeat("\0", 32);
        $h .= sprintf('%07o', 0) . "\0";
        $h .= sprintf('%07o', 0) . "\0";
        $h = str_pad($h, 512, "\0");

        $suma = array_sum(unpack('C*', $h));

        return substr_replace($h, sprintf('%06o', $suma) . "\0 ", 148, 8);
    }
}
