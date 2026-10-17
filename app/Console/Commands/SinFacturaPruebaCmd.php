<?php

namespace App\Console\Commands;

use App\Modules\Sin\Services\SinFacturaPruebaService;
use Illuminate\Console\Command;

class SinFacturaPruebaCmd extends Command
{
    protected $signature = 'sin:factura-prueba
                            {--items=2 : Cantidad de ítems}
                            {--descuento=0 : Descuento adicional global en Bs}
                            {--xml : Muestra el XML generado}';

    protected $description = 'Genera una factura de prueba y la valida contra el XSD (sin enviarla)';

    public function handle(SinFacturaPruebaService $servicio): int
    {
        try {
            $factura = $servicio->generar(
                (int) $this->option('items'),
                (float) $this->option('descuento')
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Factura de prueba generada y válida contra el XSD.');
        $this->line('ID       : ' . $factura->id_sin_factura_prueba);
        $this->line('Número   : ' . $factura->numero);
        $this->line('CUF      : ' . $factura->cuf);
        $this->line('Total Bs : ' . $factura->monto_total);

        if ($this->option('xml')) {
            $this->newLine();
            $this->line($factura->xml);
        }

        return self::SUCCESS;
    }
}
