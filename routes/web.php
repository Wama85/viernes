<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Modules\Usuarios\Http\Controllers\UsuarioController;
use App\Modules\Clientes\Http\Controllers\ClienteController;
use App\Modules\Productos\Http\Controllers\CategoriaController;
use App\Modules\Productos\Http\Controllers\ProductoController;

Route::get('/', function () {
    return view('welcome');
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
require __DIR__.'/auth.php';
