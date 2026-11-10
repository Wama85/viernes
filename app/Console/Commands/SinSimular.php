<?php

namespace App\Console\Commands;

use App\Modules\Sin\Services\SinComunicacion;
use Illuminate\Console\Command;

class SinSimular extends Command
{
    protected $signature = 'sin:simular {estado : on para simular la falta de conexión con el SIN, off para volver a la normalidad}';
    protected $description = 'Simula la falta de conexión con el SIN para ensayar la contingencia (solo ambiente de pruebas)';

    public function handle(SinComunicacion $comunicacion): int
    {
        if ((int) config('sin.ambiente') !== 2) {
            $this->error('Solo se puede simular en el ambiente de pruebas (SIN_AMBIENTE=2).');

            return self::FAILURE;
        }

        $estado = strtolower((string) $this->argument('estado'));

        if (! in_array($estado, ['on', 'off'], true)) {
            $this->error('Usa: php artisan sin:simular on   o   php artisan sin:simular off');

            return self::FAILURE;
        }

        $comunicacion->simular($estado === 'on');

        $this->info($estado === 'on'
            ? 'Simulación activada: las facturas se emitirán FUERA DE LÍNEA.'
            : 'Simulación desactivada: la comunicación con el SIN vuelve a ser real.');

        return self::SUCCESS;
    }
}
