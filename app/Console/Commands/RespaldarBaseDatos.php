<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class RespaldarBaseDatos extends Command
{
    protected $signature = 'backup:correo
                            {--conservar= : Cuántos respaldos se conservan (por defecto, el valor de config/respaldo.php)}
                            {--sin-correo : No envía el correo con los enlaces de descarga}';

    protected $description = 'Crea un respaldo cifrado de la base de datos, avisa por correo con enlaces de descarga y conserva solo los más recientes';

    public function handle(): int
    {
        $conexion = config('database.connections.' . config('database.default'));

        if (! in_array($conexion['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            return $this->fallar('El respaldo solo funciona con MySQL o MariaDB.');
        }

        $base = $conexion['database'];
        $carpeta = rtrim((string) config('respaldo.carpeta'), '/\\');
        $separador = DIRECTORY_SEPARATOR;

        if (! is_dir($carpeta) && ! @mkdir($carpeta, 0775, true) && ! is_dir($carpeta)) {
            return $this->fallar("No se pudo crear la carpeta de respaldos: {$carpeta}");
        }

        $sql = $carpeta . $separador . $base . '_' . now()->format('Y-m-d_His') . '.sql';
        $opciones = tempnam(sys_get_temp_dir(), 'resp');

        try {
            $ejecutable = $this->ejecutable();
            if ($ejecutable === null) {
                return $this->fallar('No se encontró mysqldump. Define RESPALDO_MYSQLDUMP en el .env.');
            }

            // La contraseña de la base va en un archivo temporal, no en la línea de comandos.
            $texto = "[client]\n"
                . 'user=' . $this->entrecomillar($conexion['username'] ?? 'root') . "\n"
                . 'host=' . $this->entrecomillar($conexion['host'] ?? '127.0.0.1') . "\n"
                . 'port=' . (int) ($conexion['port'] ?? 3306) . "\n";
            if (($conexion['password'] ?? '') !== '') {
                $texto .= 'password=' . $this->entrecomillar($conexion['password']) . "\n";
            }
            file_put_contents($opciones, $texto);

            $proceso = new Process([
                $ejecutable,
                '--defaults-extra-file=' . str_replace('\\', '/', $opciones),
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--default-character-set=utf8mb4',
                '--result-file=' . $sql,
                $base,
            ], null, $this->entornoProceso());
            $proceso->setTimeout(900);
            $proceso->run();

            if (! $proceso->isSuccessful()) {
                return $this->fallar('mysqldump falló: ' . trim($proceso->getErrorOutput()));
            }

            if (! is_file($sql) || filesize($sql) === 0 || ! $this->volcadoCompleto($sql)) {
                return $this->fallar('El volcado quedó incompleto; se descartó.');
            }

            // Con clave: .zip cifrado (AES-256). Sin clave: .sql.gz sin protección.
            $clave = (string) config('respaldo.clave_zip');
            if ($clave !== '') {
                $final = $carpeta . $separador . basename($sql, '.sql') . '.zip';
                $this->empaquetarCifrado($sql, $final, $clave);
            } else {
                $final = $sql . '.gz';
                $this->comprimir($sql, $final);
                $this->warn('RESPALDO_CLAVE_ZIP no está definida: el respaldo se guardó SIN contraseña.');
                Log::warning('Respaldo guardado sin contraseña (falta RESPALDO_CLAVE_ZIP).');
            }

            @chmod($final, 0640);
            $peso = round(filesize($final) / 1048576, 2);

            // Solo se conservan los últimos N respaldos.
            $conservar = $this->option('conservar') !== null
                ? max(1, (int) $this->option('conservar'))
                : (int) config('respaldo.conservar');
            $this->limpiar($carpeta, $base, $conservar);

            Log::info("Respaldo creado: {$final} ({$peso} MB)");
            $this->info("Respaldo creado: {$final} ({$peso} MB)");

            if (! $this->option('sin-correo')) {
                $this->enviarCorreo($carpeta, $base, $peso, $conservar, $clave !== '');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            return $this->fallar('Error inesperado: ' . $e->getMessage());
        } finally {
            @unlink($opciones);
            if (is_file($sql)) {
                @unlink($sql); // volcado sin proteger: nunca se deja en disco
            }
        }
    }

    private function ejecutable(): ?string
    {
        $configurado = config('respaldo.mysqldump');
        if ($configurado) {
            return is_file($configurado) ? $configurado : null;
        }

        $candidatos = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            'D:/xampp/mysql/bin/mysqldump.exe',
            'C:/xampp/mysql/bin/mysqldump.exe',
        ];
        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return 'mysqldump'; // por si está en el PATH
    }

        /**
     * En Windows, mysqldump necesita SystemRoot para iniciar la red. Cuando lo lanza Apache
     * (botón «Crear respaldo ahora») a veces no lo recibe y falla con el error 10106.
     */
    private function entornoProceso(): array
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return []; // En Linux no hace falta
        }

        $raiz = getenv('SystemRoot')
            ?: getenv('SYSTEMROOT')
            ?: ($_SERVER['SystemRoot'] ?? $_SERVER['SYSTEMROOT'] ?? null)
            ?: 'C:\\Windows';

        return ['SystemRoot' => $raiz, 'windir' => $raiz];
    }

    private function entrecomillar(string $valor): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $valor) . '"';
    }

    /** Un volcado completo termina con la línea "-- Dump completed". */
    private function volcadoCompleto(string $archivo): bool
    {
        $tamano = filesize($archivo);
        $f = fopen($archivo, 'rb');
        fseek($f, max(0, $tamano - 400));
        $cola = fread($f, 400);
        fclose($f);

        return str_contains($cola, 'Dump completed');
    }

    /** Guarda el volcado dentro de un .zip cifrado con AES-256. Si no puede cifrar, falla. */
    private function empaquetarCifrado(string $origen, string $destino, string $clave): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'Falta la extensión zip de PHP, necesaria para proteger el respaldo con contraseña. '
                . 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . '), binario: ' . PHP_BINARY
                . ', php.ini: ' . (php_ini_loaded_file() ?: 'ninguno')
                . ', zip cargada: ' . (extension_loaded('zip') ? 'sí' : 'no')
            );
        }

        $zip = new ZipArchive();
        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo zip del respaldo.');
        }

        $interno = basename($origen);
        $zip->addFile($origen, $interno);
        $zip->setCompressionName($interno, ZipArchive::CM_DEFLATE);

        if (! $zip->setEncryptionName($interno, ZipArchive::EM_AES_256, $clave)) {
            $zip->close();
            @unlink($destino);
            throw new RuntimeException('Este PHP no puede cifrar zip con AES-256. Actualiza libzip o quita RESPALDO_CLAVE_ZIP.');
        }

        // El archivo se escribe al cerrar; el volcado original debe seguir existiendo hasta aquí.
        if (! $zip->close() || ! is_file($destino) || filesize($destino) === 0) {
            @unlink($destino);
            throw new RuntimeException('No se pudo escribir el zip cifrado.');
        }
    }

    private function comprimir(string $origen, string $destino): void
    {
        $entrada = fopen($origen, 'rb');
        $salida = gzopen($destino, 'wb9');

        while (! feof($entrada)) {
            gzwrite($salida, fread($entrada, 1048576));
        }

        fclose($entrada);
        gzclose($salida);
        unlink($origen);
    }

    /** Respaldos existentes (.zip y .sql.gz), del más nuevo al más antiguo. */
    private function archivos(string $carpeta, string $base): array
    {
        $lista = array_merge(
            glob($carpeta . DIRECTORY_SEPARATOR . $base . '_*.zip') ?: [],
            glob($carpeta . DIRECTORY_SEPARATOR . $base . '_*.sql.gz') ?: []
        );
        rsort($lista); // el nombre lleva la fecha y hora

        return $lista;
    }

    /** Deja solo los $conservar respaldos más recientes. */
    private function limpiar(string $carpeta, string $base, int $conservar): void
    {
        foreach (array_slice($this->archivos($carpeta, $base), $conservar) as $archivo) {
            @unlink($archivo);
        }
    }

    /**
     * Correo al propietario con un enlace de descarga por cada respaldo guardado.
     * La contraseña del zip NUNCA va en el correo.
     * Un fallo aquí no invalida el respaldo: solo se avisa en pantalla y en el log.
     */
    private function enviarCorreo(string $carpeta, string $base, float $peso, int $conservar, bool $protegido): void
    {
        $destinatarios = array_values(array_filter(array_map('trim', explode(',', (string) config('respaldo.correo')))));
        if (! $destinatarios) {
            return;
        }

        try {
            $dias = max(1, (int) config('respaldo.enlace_dias'));
            $vence = now()->addDays($dias);

            $filas = '';
            foreach ($this->archivos($carpeta, $base) as $n => $ruta) {
                $nombre = basename($ruta);
                $enlace = URL::temporarySignedRoute('respaldos.descargar', $vence, ['archivo' => $nombre]);
                $mb = number_format(filesize($ruta) / 1048576, 2);
                $fecha = date('d/m/Y H:i', filemtime($ruta));
                $nuevo = $n === 0 ? ' <span style="background:#dcfce7;color:#166534;padding:1px 6px;border-radius:4px;font-size:11px">NUEVO</span>' : '';
                $candado = str_ends_with($nombre, '.zip')
                    ? ' <span style="color:#6b7280;font-size:11px">con contraseña</span>'
                    : ' <span style="color:#b45309;font-size:11px">sin contraseña</span>';

                $filas .= '<tr>'
                    . '<td style="padding:8px 10px;border-bottom:1px solid #e5e7eb">' . e($fecha) . $nuevo . $candado . '</td>'
                    . '<td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:right">' . e($mb) . ' MB</td>'
                    . '<td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:right">'
                    . '<a href="' . e($enlace) . '" style="color:#4f46e5;font-weight:bold">Descargar</a></td>'
                    . '</tr>';
            }

            $aviso = $protegido
                ? '<p>Los respaldos están cifrados. Para abrirlos necesitas la contraseña de respaldos configurada en el sistema '
                    . '(no se envía por correo) y un programa como 7-Zip o WinRAR.</p>'
                : '';
            // Si nadie descargó una copia en los últimos 7 días, el correo lo recuerda.
            $recordatorio = '';
            try {
                $ultimaDescarga = \Illuminate\Support\Facades\DB::table('respaldo_descargas')->max('created_at');
                if (! $ultimaDescarga
                    || now()->timestamp - \Illuminate\Support\Carbon::parse($ultimaDescarga)->timestamp >= 7 * 86400) {
                    $recordatorio = '<p style="background:#fef3c7;color:#92400e;padding:8px 10px;border-radius:6px">'
                        . '<strong>Recordatorio:</strong> hace 7 días o más que no se descarga una copia. '
                        . 'Descarga una y guárdala fuera del servidor.</p>';
                }
            } catch (Throwable $e) {
                // Sin tabla de descargas, el correo sale igual, sin recordatorio.
            }

            $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827;max-width:560px">'
                . '<h2 style="margin:0 0 12px">Respaldo de la base de datos</h2>'
                . '<p>Se generó el respaldo de <strong>' . e($base) . '</strong> el ' . e(now()->format('d/m/Y H:i')) . ' (' . e($peso) . ' MB).</p>'
                . '<p>Elige el que quieras descargar. Para hacerlo debes iniciar sesión como administrador; '
                . 'los enlaces vencen el ' . e($vence->format('d/m/Y H:i')) . '.</p>'
                . $aviso . $recordatorio
                . '<table style="width:100%;border-collapse:collapse;margin:12px 0">' . $filas . '</table>'
                . '<p style="color:#6b7280;font-size:12px">El sistema conserva los últimos ' . (int) $conservar . ' respaldos en el servidor. '
                . 'Para restaurar: descomprime el .zip, y en phpMyAdmin usa Importar con el archivo .sql.<br>Mensaje automático.</p>'
                . '</div>';

            $asunto = config('app.name') . ' · Respaldo de la base de datos · ' . now()->format('d/m/Y H:i');

            Mail::html($html, function ($mensaje) use ($destinatarios, $asunto) {
                $mensaje->to($destinatarios)->subject($asunto);
            });

            $this->line('Correo enviado a: ' . implode(', ', $destinatarios));
            Log::info('Respaldo: correo enviado a ' . implode(', ', $destinatarios));
        } catch (Throwable $e) {
            Log::warning('Respaldo: no se pudo enviar el correo: ' . $e->getMessage());
            $this->warn('No se pudo enviar el correo: ' . $e->getMessage());
        }
    }

    private function fallar(string $mensaje): int
    {
        Log::error('Respaldo de base de datos: ' . $mensaje);
        $this->error($mensaje);

        return self::FAILURE;
    }
}
