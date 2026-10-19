<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Modules\Auditoria\Http\Controllers\AuditoriaController;
use App\Modules\Clientes\Http\Controllers\ClienteController;
use App\Modules\Cotizaciones\Http\Controllers\CotizacionController;
use App\Modules\Cotizaciones\Http\Controllers\CotizacionPdfController;
use App\Modules\Cotizaciones\Http\Controllers\SeguimientoController;
use App\Modules\Empresa\Http\Controllers\EmpresaController;
use App\Modules\Facturas\Http\Controllers\FacturaController;
use App\Modules\Papelera\Http\Controllers\PapeleraController;
use App\Modules\Productos\Http\Controllers\CategoriaController;
use App\Modules\Productos\Http\Controllers\MovimientoStockController;
use App\Modules\Productos\Http\Controllers\ProductoController;
use App\Modules\Recibos\Http\Controllers\ReciboController;
use App\Modules\Usuarios\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RespaldoController;


Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Público (enlace firmado que recibe el cliente por WhatsApp)
|--------------------------------------------------------------------------
*/
Route::get('cotizaciones/{cotizacion}/documento', [CotizacionPdfController::class, 'publico'])
    ->middleware('signed')
    ->name('cotizaciones.documento');

/*
|--------------------------------------------------------------------------
| Usuarios autenticados (Administrador y Vendedor)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('verified')
        ->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Clientes
    Route::resource('clientes', ClienteController::class)->except(['show', 'destroy']);
    Route::patch('clientes/{cliente}/cambiar-estado', [ClienteController::class, 'cambiarEstado'])
        ->name('clientes.cambiarEstado');

    // Productos (solo consulta)
    Route::get('productos', [ProductoController::class, 'index'])->name('productos.index');

    // Cotizaciones
    Route::resource('cotizaciones', CotizacionController::class)
        ->except(['destroy'])
        ->parameters(['cotizaciones' => 'cotizacion']);
    Route::get('cotizaciones/{cotizacion}/pdf', CotizacionPdfController::class)->name('cotizaciones.pdf');
    Route::post('cotizaciones/{cotizacion}/enviar/correo', [SeguimientoController::class, 'correo'])
        ->name('cotizaciones.enviar.correo');
    Route::post('cotizaciones/{cotizacion}/enviar/whatsapp', [SeguimientoController::class, 'whatsapp'])
        ->name('cotizaciones.enviar.whatsapp');
    Route::post('cotizaciones/{cotizacion}/enviar/manual', [SeguimientoController::class, 'manual'])
        ->name('cotizaciones.enviar.manual');

    // Recibos
    Route::get('recibos', [ReciboController::class, 'index'])->name('recibos.index');
    Route::get('cotizaciones/{cotizacion}/recibos/create', [ReciboController::class, 'create'])->name('recibos.create');
    Route::post('cotizaciones/{cotizacion}/recibos', [ReciboController::class, 'store'])->name('recibos.store');
    Route::get('recibos/{recibo}', [ReciboController::class, 'show'])->name('recibos.show');
    Route::get('recibos/{recibo}/pdf', [ReciboController::class, 'pdf'])->name('recibos.pdf');

    // Facturas
    Route::get('facturas', [FacturaController::class, 'index'])->name('facturas.index');
    Route::get('cotizaciones/{cotizacion}/facturas/create', [FacturaController::class, 'create'])->name('facturas.create');
    Route::post('cotizaciones/{cotizacion}/facturas', [FacturaController::class, 'store'])->name('facturas.store');
    Route::get('facturas/{factura}', [FacturaController::class, 'show'])->name('facturas.show');
    Route::patch('facturas/{factura}/enviar-sin', [FacturaController::class, 'enviarSin'])->name('facturas.enviar-sin');
    Route::get('facturas/{factura}/pdf', [FacturaController::class, 'pdf'])->name('facturas.pdf');

    // Respaldos
    Route::middleware('auth')->group(function () {
    Route::get('/respaldos', [RespaldoController::class, 'index'])->name('respaldos.index');
    Route::post('/respaldos', [RespaldoController::class, 'crear'])->name('respaldos.crear');
    Route::get('/respaldos/archivo/{archivo}', [RespaldoController::class, 'bajar'])->name('respaldos.bajar');
});
});

/*
|--------------------------------------------------------------------------
| Solo Administrador
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->group(function () {
    // Usuarios
    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/cambiar-estado', [UsuarioController::class, 'cambiarEstado'])
        ->name('usuarios.cambiarEstado');

    // Categorías
    Route::resource('categorias', CategoriaController::class)->except(['show', 'destroy']);
    Route::patch('categorias/{categoria}/cambiar-estado', [CategoriaController::class, 'cambiarEstado'])
        ->name('categorias.cambiarEstado');

    // Productos y stock
    Route::resource('productos', ProductoController::class)->except(['index', 'show', 'destroy']);
    Route::patch('productos/{producto}/cambiar-estado', [ProductoController::class, 'cambiarEstado'])
        ->name('productos.cambiarEstado');
    Route::get('productos/{producto}/movimientos', [MovimientoStockController::class, 'index'])
        ->name('productos.movimientos');
    Route::post('productos/{producto}/movimientos', [MovimientoStockController::class, 'store'])
        ->name('productos.movimientos.store');

    // Cotizaciones
    Route::patch('cotizaciones/{cotizacion}/aprobar', [CotizacionController::class, 'aprobar'])
        ->name('cotizaciones.aprobar');
    Route::patch('cotizaciones/{cotizacion}/anular', [CotizacionController::class, 'anular'])
        ->name('cotizaciones.anular');

    // Empresa
    Route::get('empresa', [EmpresaController::class, 'edit'])->name('empresa.edit');
    Route::put('empresa', [EmpresaController::class, 'update'])->name('empresa.update');

    // Recibos
    Route::get('recibos/{recibo}/edit', [ReciboController::class, 'edit'])->name('recibos.edit');
    Route::put('recibos/{recibo}', [ReciboController::class, 'update'])->name('recibos.update');
    Route::patch('recibos/{recibo}/aprobar', [ReciboController::class, 'aprobar'])->name('recibos.aprobar');
    Route::patch('recibos/{recibo}/anular', [ReciboController::class, 'anular'])->name('recibos.anular');

    // Facturas
    Route::patch('facturas/{factura}/aprobar', [FacturaController::class, 'aprobar'])->name('facturas.aprobar');
    Route::patch('facturas/{factura}/anular', [FacturaController::class, 'anular'])->name('facturas.anular');

    // Auditoría
    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');


    // Papelera (clientes, productos y categorías dados de baja)
    Route::get('papelera', [PapeleraController::class, 'index'])->name('papelera.index');
    Route::patch('papelera/{tipo}/{id}/restaurar', [PapeleraController::class, 'restaurar'])
        ->whereIn('tipo', ['clientes', 'productos', 'categorias'])
        ->whereNumber('id')
        ->name('papelera.restaurar');
    Route::delete('papelera/{tipo}/{id}', [PapeleraController::class, 'eliminar'])
        ->whereIn('tipo', ['clientes', 'productos', 'categorias'])
        ->whereNumber('id')
        ->name('papelera.eliminar');
});

Route::get('/respaldos/descargar/{archivo}', [RespaldoController::class, 'descargar'])
    ->middleware(['auth', 'signed'])
    ->name('respaldos.descargar');


require __DIR__ . '/auth.php';
