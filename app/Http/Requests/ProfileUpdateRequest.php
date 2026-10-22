<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Limpia el nombre antes de validar. */
    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');

        $this->merge([
            'nombre' => is_string($nombre) ? trim(preg_replace('/\s+/u', ' ', $nombre)) : $nombre,
        ]);
    }

    /**
     * Solo el nombre se edita desde el perfil; el correo lo cambia el administrador.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\p{L} .\'\-]+$/u'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.min'      => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max'      => 'El nombre admite como máximo 100 caracteres.',
            'nombre.regex'    => 'El nombre solo admite letras, espacios, punto, apóstrofo y guion.',
        ];
    }
}
