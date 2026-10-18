<?php

return [
    // Ruta de mysqldump. Si está vacía, se busca en las rutas habituales (Linux, XAMPP) y en el PATH.
    'mysqldump' => env('RESPALDO_MYSQLDUMP'),

    // Carpeta de respaldos dentro del servidor (no es accesible desde el navegador).
    'carpeta' => env('RESPALDO_CARPETA', storage_path('app/respaldos')),

    // Cuántos respaldos se conservan (los más recientes).
    'conservar' => max(1, (int) env('RESPALDO_CONSERVAR', 8)),

    // Correo(s) del propietario. Varios separados por coma. Vacío = no enviar.
    'correo' => env('RESPALDO_CORREO'),

    // Días de validez de los enlaces de descarga del correo.
    'enlace_dias' => (int) env('RESPALDO_ENLACE_DIAS', 7),

    // Contraseña del .zip cifrado (AES-256). Vacía = el respaldo se guarda sin contraseña.
    'clave_zip' => env('RESPALDO_CLAVE_ZIP'),
];
