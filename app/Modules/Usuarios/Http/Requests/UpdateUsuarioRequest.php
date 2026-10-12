<?php

namespace App\Modules\Usuarios\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $idUsuario = $this->route('usuario');

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:100',
                Rule::unique('usuarios', 'email')->ignore($idUsuario, 'id_usuario'),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'id_rol' => ['required', 'exists:roles,id_rol'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe otro usuario con este correo.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'id_rol.required' => 'Debes seleccionar un rol.',
            'id_rol.exists' => 'El rol seleccionado no es válido.',
        ];
    }
}
