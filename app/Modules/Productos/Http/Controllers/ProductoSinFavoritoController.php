<?php

namespace App\Modules\Productos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Models\ProductoSin;
use Illuminate\Http\JsonResponse;

class ProductoSinFavoritoController extends Controller
{
    /** Marca o desmarca un producto del SIN como favorito. */
    public function alternar(ProductoSin $productoSin): JsonResponse
    {
        $productoSin->update(['favorito' => ! $productoSin->favorito]);

        return response()->json(['favorito' => (bool) $productoSin->favorito]);
    }
}
