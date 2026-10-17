<?php

namespace App\Support;

class Whatsapp
{
    /**
     * Convierte un teléfono boliviano a formato internacional (591XXXXXXXX).
     * Solo los celulares (8 dígitos que empiezan con 6 o 7) sirven para WhatsApp.
     */
    public static function numero(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);
        $digitos = ltrim($digitos, '0');

        if (str_starts_with($digitos, '591') && strlen($digitos) === 11) {
            $digitos = substr($digitos, 3);
        }

        if (strlen($digitos) === 8 && in_array($digitos[0], ['6', '7'], true)) {
            return '591' . $digitos;
        }

        return null;
    }

    /** Primer teléfono de la lista que sirva para WhatsApp. */
    public static function primero(iterable $telefonos): ?string
    {
        foreach ($telefonos as $telefono) {
            if ($numero = self::numero($telefono)) {
                return $numero;
            }
        }

        return null;
    }
}
