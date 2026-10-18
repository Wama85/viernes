<?php

namespace App\Modules\Usuarios\Http\Requests\Concerns;

use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Validation\Rule;

trait ReglasUsuario
{
    /** Limpia los datos antes de validar. La contraseña no se toca. */
    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');
        $email = $this->input('email');

        $this->merge([
            'nombre' => is_string($nombre) ? trim(preg_replace('/\s+/u', ' ', $nombre)) : $nombre,
            'email'  => is_string($email) ? mb_strtolower(trim($email)) : $email,
        ]);
    }

    protected function reglasUsuario(?Usuario $usuario = null): array
    {
        $unicoEmail = Rule::unique('usuarios', 'email');
        if ($usuario) {
            $unicoEmail->ignore($usuario->getKey(), 'id_usuario');
        }

        // El rol debe estar activo. Al editar, se permite conservar el rol actual.
        $rolActual = $usuario?->id_rol;
        $rolValido = Rule::exists('roles', 'id_rol')->where(function ($consulta) use ($rolActual) {
            $consulta->where(function ($grupo) use ($rolActual) {
                $grupo->where('rol_estado', 1);
                if ($rolActual) {
                    $grupo->orWhere('id_rol', $rolActual);
                }
            });
        });

        $letrasYNumeros = function ($atributo, $valor, $fallar) {
            if (! preg_match('/\p{L}/u', (string) $valor) || ! preg_match('/\d/', (string) $valor)) {
                $fallar('La contraseña debe incluir al menos una letra y un número.');
            }
        };

        return [
            'nombre' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\p{L} .\'\-]+$/u'],
            'email'  => [
                'required', 'string', 'email', 'max:100',
                'regex:/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/',
                $unicoEmail,
            ],
            'password' => $usuario
                ? ['nullable', 'string', 'min:8', 'max:72', $letrasYNumeros]
                : ['required', 'string', 'min:8', 'max:72', $letrasYNumeros],
            'password_confirmation' => $usuario
                ? ['required_with:password', 'same:password']
                : ['required', 'same:password'],
            'id_rol' => ['required', $rolValido],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.min'      => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max'      => 'El nombre admite como máximo 100 caracteres.',
            'nombre.regex'    => 'El nombre solo admite letras, espacios, punto, apóstrofo y guion.',

            'email.required' => 'El correo es obligatorio.',
            'email.email'    => 'El correo no tiene un formato válido.',
            'email.regex'    => 'El correo no tiene un formato válido.',
            'email.max'      => 'El correo admite como máximo 100 caracteres.',
            'email.unique'   => 'Ya existe un usuario con este correo (puede estar dado de baja).',

            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max'      => 'La contraseña admite como máximo 72 caracteres.',

            'password_confirmation.required'      => 'Confirma la contraseña.',
            'password_confirmation.required_with' => 'Confirma la contraseña.',
            'password_confirmation.same'          => 'Las contraseñas no coinciden.',

            'id_rol.required' => 'Debes seleccionar un rol.',
            'id_rol.exists'   => 'El rol seleccionado no es válido o está dado de baja.',
        ];
    }
}
