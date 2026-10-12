<?php

namespace App\Modules\Clientes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cliente = $this->route('cliente');

        return [
            'id_tipo_documento' => ['required', 'exists:tipos_documento,id_tipo_documento'],
            'documento_identidad' => [
                'required', 'string', 'max:20',
                Rule::unique('clientes', 'documento_identidad')
                    ->where(fn ($query) => $query->where('complemento', $this->complemento))
                    ->ignore($cliente, 'id_cliente'),
            ],
            'complemento' => ['nullable', 'string', 'max:10'],
            'razon_social' => ['required', 'string', 'max:100'],
            'nombre_responsable' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],

            'correos' => ['nullable', 'array'],
            'correos.*' => ['nullable', 'email', 'max:100', 'distinct'],

            'telefonos' => ['nullable', 'array'],
            'telefonos.*' => ['nullable', 'string', 'max:20', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_tipo_documento.required' => 'Debes seleccionar el tipo de documento.',
            'id_tipo_documento.exists' => 'El tipo de documento seleccionado no es válido.',
            'documento_identidad.required' => 'El número de documento es obligatorio.',
            'documento_identidad.unique' => 'Ya existe otro cliente con este documento.',
            'razon_social.required' => 'La razón social o nombre es obligatorio.',
            'correos.*.email' => 'Uno de los correos no tiene un formato válido.',
            'correos.*.distinct' => 'Hay un correo repetido.',
            'telefonos.*.distinct' => 'Hay un teléfono repetido.',
        ];
    }
}
