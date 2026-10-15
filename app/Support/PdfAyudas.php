<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class PdfAyudas
{
    /** Convierte un archivo subido (logo, sello) en una imagen incrustable en el PDF. */
    public static function imagen(?string $ruta): ?string
    {
        if (! $ruta) {
            return null;
        }

        $candidatos = [
            storage_path('app/public/' . $ruta),
            public_path($ruta),
            public_path('storage/' . $ruta),
            storage_path('app/' . $ruta),
        ];

        foreach ($candidatos as $archivo) {
            if (is_file($archivo)) {
                $mime = mime_content_type($archivo) ?: 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($archivo));
            }
        }

        return null;
    }

    public static function qr(string $texto): string
    {
        return Builder::create()
            ->writer(new PngWriter())
            ->data($texto)
            ->size(200)
            ->margin(4)
            ->build()
            ->getDataUri();
    }

    /** Ejemplo: 13 de octubre del 2026 */
    public static function fechaLarga(CarbonInterface $fecha): string
    {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return $fecha->format('d') . ' de ' . $meses[(int) $fecha->format('n')] . ' del ' . $fecha->format('Y');
    }
}
