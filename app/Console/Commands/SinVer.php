<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Services\SinCliente;
use Illuminate\Console\Command;

class SinVer extends Command
{
    protected $signature = 'sin:ver {catalogo : Nombre del catálogo guardado, o la palabra "tipos"} {--buscar= : Palabras separadas por coma para filtrar}';

    protected $description = 'Muestra un catálogo guardado del SIN o los tipos del servicio de facturación';

    public function handle(SinCliente $sin): int
    {
        $palabras = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('buscar')))));

        $coincide = function (string $texto) use ($palabras): bool {
            if (! $palabras) {
                return true;
            }
            $patron = '/' . implode('|', array_map(fn ($p) => preg_quote($p, '/'), $palabras)) . '/i';

            return (bool) preg_match($patron, $texto);
        };

        if ($this->argument('catalogo') === 'tipos') {
            $cliente = $sin->servicio('ServicioFacturacionCompraVenta');

            foreach ($cliente->__getTypes() as $tipo) {
                if ($coincide($tipo)) {
                    $this->line($tipo);
                }
            }

            return self::SUCCESS;
        }

        $fila = SinCatalogo::where('catalogo', $this->argument('catalogo'))->first();

        if (! $fila) {
            $this->error('No existe ese catálogo en la base de datos.');

            return self::FAILURE;
        }

        $datos = $fila->datos;
        $lista = $datos['listaCodigos'] ?? null;

        if (! $lista) {
            foreach ((array) $datos as $valor) {
                if (is_array($valor)) {
                    $lista = $valor;
                    break;
                }
            }
        }

        $lista ??= (array) $datos;

        if (isset($lista['codigoClasificador'])) {
            $lista = [$lista];
        }

        foreach ($lista as $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $linea = implode(' | ', array_map(
                fn ($v) => is_scalar($v) ? (string) $v : json_encode($v),
                $fila
            ));

            if ($coincide($linea)) {
                $this->line($linea);
            }
        }

        return self::SUCCESS;
    }
}
