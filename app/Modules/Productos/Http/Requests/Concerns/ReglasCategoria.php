<?php

namespace App\Modules\Productos\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ReglasCategoria
{
    /** Limpia el nombre antes de validar: espacios y mayúsculas. */
    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre_categoria');

        $this->merge([
            'nombre_categoria' => is_string($nombre)
                ? mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $nombre)), 'UTF-8')
                : $nombre,
        ]);
    }

    protected function reglasCategoria($categoria = null): array
    {
        $unico = Rule::unique('categorias', 'nombre_categoria');
        if ($categoria) {
            $unico->ignore($categoria->getKey(), 'id_categoria');
        }

        return [
            'nombre_categoria' => [
                'required', 'string', 'min:2', 'max:100',
                'regex:/^[\p{L}\p{N} .,&\'"\-\/()#°ºª+]+$/u',
                $unico,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_categoria.required' => 'El nombre de la categoría es obligatorio.',
            'nombre_categoria.min'      => 'El nombre debe tener al menos 2 caracteres.',
            'nombre_categoria.max'      => 'El nombre admite como máximo 100 caracteres.',
            'nombre_categoria.regex'    => 'El nombre tiene caracteres no permitidos. Usa letras, números y signos comunes.',
            'nombre_categoria.unique'   => 'Ya existe una categoría con este nombre (puede estar dada de baja: revisa la Papelera).',
        ];
    }
}
