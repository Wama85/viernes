<?php

namespace App\Modules\Productos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_producto_sin' => ['required', 'exists:productos_sin,id_producto_sin'],
            'id_categoria' => ['required', 'exists:categorias,id_categoria'],
            'id_unidad_medida' => ['required', 'exists:unidades_medida,id_unidad_medida'],
            'descripcion_producto' => ['required', 'string', 'max:500'],
            'detalle' => ['nullable', 'string', 'max:5000'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'costo_compra' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'controla_stock' => ['nullable', 'boolean'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_producto_sin.required' => 'Debes seleccionar el producto del SIN.',
            'id_producto_sin.exists' => 'El producto del SIN seleccionado no es válido.',
            'id_categoria.required' => 'Debes seleccionar una categoría.',
            'id_unidad_medida.required' => 'Debes seleccionar la unidad de medida.',
            'descripcion_producto.required' => 'La descripción del producto es obligatoria.',
            'descripcion_producto.max' => 'La descripción no puede pasar de 500 caracteres.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'precio_venta.numeric' => 'El precio debe ser un número.',
            'precio_venta.min' => 'El precio no puede ser negativo.',
            'stock.min' => 'El stock no puede ser negativo.',
            'stock_minimo.min' => 'El stock mínimo no puede ser negativo.',
        ];
    }
}
