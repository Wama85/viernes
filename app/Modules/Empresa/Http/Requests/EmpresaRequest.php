<?php

namespace App\Modules\Empresa\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_empresa'    => ['required', 'string', 'max:150'],
            'propietario'       => ['required', 'string', 'max:100'],
            'carnet'            => ['required', 'string', 'max:20'],
            'nit'               => ['nullable', 'string', 'max:20'],
            'direccion'         => ['required', 'string', 'max:255'],
            'ciudad_pais'       => ['nullable', 'string', 'max:100'],
            'telefonos'         => ['required', 'string', 'max:100'],
            'correo'            => ['required', 'email', 'max:100'],
            'sitio_web'         => ['nullable', 'string', 'max:100'],
            'cuentas_bancarias' => ['nullable', 'string', 'max:1000'],
            'formas_pago'       => ['nullable', 'string', 'max:255'],
            'logo'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sello'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
