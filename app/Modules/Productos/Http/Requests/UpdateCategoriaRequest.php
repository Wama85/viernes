<?php

namespace App\Modules\Productos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_categoria' => [
                'required', 'string', 'max:100',
                Rule::unique('categorias', 'nombre_categoria')
                    ->ignore($this->route('categoria'), 'id_categoria'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_categoria.required' => 'El nombre de la categoría es obligatorio.',
            'nombre_categoria.unique' => 'Ya existe otra categoría con este nombre.',
        ];
    }
}
