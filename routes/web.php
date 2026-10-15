<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Modules\Usuarios\Http\Controllers\UsuarioController;
use App\Modules\Clientes\Http\Controllers\ClienteController;
use App\Modules\Productos\Http\Controllers\CategoriaController;
use App\Modules\Productos\Http\Controllers\ProductoController;
use App\Modules\Cotizaciones\Http\Controllers\CotizacionController;
use App\Modules\Empresa\Http\Controllers\EmpresaController;
use App\Modules\Recibos\Http\Controllers\ReciboController;
use App\Modules\Cotizaciones\Http\Controllers\SeguimientoController;
use App\Modules\Cotizaciones\Http\Controllers\CotizacionPdfController;
use App\Modules\Facturas\Http\Controllers\FacturaController;
use App\Http\Controllers\DashboardController;
use App\Modules\Auditoria\Http\Controllers\AuditoriaController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/cambiar-estado', [UsuarioController::class, 'cambiarEstado'])
        ->name('usuarios.cambiarEstado');
});

Route::middleware('auth')->group(function () {
    Route::resource('clientes', ClienteController::class)->except(['show', 'destroy']);
    Route::patch('clientes/{cliente}/cambiar-estado', [ClienteController::class, 'cambiarEstado'])
        ->name('clientes.cambiarEstado');
});


Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('categorias', CategoriaController::class)->except(['show', 'destroy']);
    Route::patch('categorias/{categoria}/cambiar-estado', [CategoriaController::class, 'cambiarEstado'])
        ->name('categorias.cambiarEstado');
});

Route::middleware('auth')->get('productos', [ProductoController::class, 'index'])
    ->name('productos.index');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('productos', ProductoController::class)->except(['index', 'show', 'destroy']);
    Route::patch('productos/{producto}/cambiar-estado', [ProductoController::class, 'cambiarEstado'])
        ->name('productos.cambiarEstado');
});
Route::middleware('auth')->group(function () {
    Route::resource('cotizaciones', CotizacionController::class)
        ->except(['destroy'])
        ->parameters(['cotizaciones' => 'cotizacion']);
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::patch('cotizaciones/{cotizacion}/aprobar', [CotizacionController::class, 'aprobar'])
        ->name('cotizaciones.aprobar');
    Route::patch('cotizaciones/{cotizacion}/anular', [CotizacionController::class, 'anular'])
        ->name('cotizaciones.anular');
});
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('empresa', [EmpresaController::class, 'edit'])->name('empresa.edit');
    Route::put('empresa', [EmpresaController::class, 'update'])->name('empresa.update');
});


Route::middleware('auth')->group(function () {
    Route::get('recibos', [ReciboController::class, 'index'])->name('recibos.index');
    Route::get('cotizaciones/{cotizacion}/recibos/create', [ReciboController::class, 'create'])->name('recibos.create');
    Route::post('cotizaciones/{cotizacion}/recibos', [ReciboController::class, 'store'])->name('recibos.store');
    Route::get('recibos/{recibo}', [ReciboController::class, 'show'])->name('recibos.show');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('recibos/{recibo}/edit', [ReciboController::class, 'edit'])->name('recibos.edit');
    Route::put('recibos/{recibo}', [ReciboController::class, 'update'])->name('recibos.update');
    Route::patch('recibos/{recibo}/aprobar', [ReciboController::class, 'aprobar'])->name('recibos.aprobar');
    Route::patch('recibos/{recibo}/anular', [ReciboController::class, 'anular'])->name('recibos.anular');
});
Route::patch('cotizaciones/{cotizacion}/enviada', [SeguimientoController::class, 'marcarEnviada'])
    ->name('cotizaciones.enviada');
Route::get('cotizaciones/{cotizacion}/pdf', CotizacionPdfController::class)
    ->name('cotizaciones.pdf');
Route::middleware('auth')->group(function () {
    Route::get('recibos', [ReciboController::class, 'index'])->name('recibos.index');
    Route::get('cotizaciones/{cotizacion}/recibos/create', [ReciboController::class, 'create'])->name('recibos.create');
    Route::post('cotizaciones/{cotizacion}/recibos', [ReciboController::class, 'store'])->name('recibos.store');
    Route::get('recibos/{recibo}', [ReciboController::class, 'show'])->name('recibos.show');
    Route::get('recibos/{recibo}/pdf', [ReciboController::class, 'pdf'])->name('recibos.pdf');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('recibos/{recibo}/edit', [ReciboController::class, 'edit'])->name('recibos.edit');
    Route::put('recibos/{recibo}', [ReciboController::class, 'update'])->name('recibos.update');
    Route::patch('recibos/{recibo}/aprobar', [ReciboController::class, 'aprobar'])->name('recibos.aprobar');
    Route::patch('recibos/{recibo}/anular', [ReciboController::class, 'anular'])->name('recibos.anular');
});

Route::middleware('auth')->group(function () {
    Route::get('facturas', [FacturaController::class, 'index'])->name('facturas.index');
    Route::get('cotizaciones/{cotizacion}/facturas/create', [FacturaController::class, 'create'])->name('facturas.create');
    Route::post('cotizaciones/{cotizacion}/facturas', [FacturaController::class, 'store'])->name('facturas.store');
    Route::get('facturas/{factura}', [FacturaController::class, 'show'])->name('facturas.show');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::patch('facturas/{factura}/aprobar', [FacturaController::class, 'aprobar'])->name('facturas.aprobar');
    Route::patch('facturas/{factura}/anular', [FacturaController::class, 'anular'])->name('facturas.anular');
});
Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
require __DIR__.'/auth.php';
