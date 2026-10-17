<?php

namespace App\Modules\Clientes\Http\Requests\Concerns;

use App\Modules\Clientes\Models\TipoDocumento;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ReglasCliente
{
    /** Limpia los datos antes de validar: espacios, mayúsculas y vacíos a null. */
    protected function prepareForValidation(): void
    {
        $limpio = function ($valor) {
            if (! is_string($valor)) {
                return $valor;
            }

            $valor = trim(preg_replace('/\s+/u', ' ', $valor));

            return $valor === '' ? null : $valor;
        };
        $mayusculas = fn ($valor) => is_string($valor) ? mb_strtoupper($valor) : $valor;
        $minusculas = fn ($valor) => is_string($valor) ? mb_strtolower($valor) : $valor;

        $documento = $this->input('documento_identidad');

        $this->merge([
            'documento_identidad' => is_string($documento)
                ? mb_strtoupper(preg_replace('/\s+/u', '', $documento))
                : $documento,
            'complemento'        => $mayusculas($limpio($this->input('complemento'))),
            'razon_social'       => $mayusculas($limpio($this->input('razon_social'))),
            'nombre_responsable' => $mayusculas($limpio($this->input('nombre_responsable'))),
            'direccion'          => $limpio($this->input('direccion')),
            'correos'            => collect((array) $this->input('correos', []))
                                        ->map(fn ($c) => $minusculas($limpio($c)))->all(),
            'telefonos'          => collect((array) $this->input('telefonos', []))
                                        ->map(fn ($t) => $limpio($t))->all(),
        ]);
    }

    protected function reglasCliente($cliente = null): array
    {
        $unico = Rule::unique('clientes', 'documento_identidad')
            ->where(fn ($consulta) => $consulta
                ->where('id_tipo_documento', $this->input('id_tipo_documento'))
                ->where('complemento', $this->input('complemento')));

        if ($cliente) {
            $unico->ignore($cliente, 'id_cliente');
        }

        return [
            'id_tipo_documento'   => ['required', 'exists:tipos_documento,id_tipo_documento'],
            'documento_identidad' => ['required', 'string', 'max:20', $unico],
            'complemento'         => ['nullable', 'string', 'regex:/^[0-9A-Z]{1,2}$/'],
            'razon_social'        => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\p{L}\p{N} .,&\'"\-\/()#°ºª+]+$/u'],
            'nombre_responsable'  => ['nullable', 'string', 'min:3', 'max:100', 'regex:/^[\p{L} .\'\-]+$/u'],
            'direccion'           => ['nullable', 'string', 'min:5', 'max:200'],

            'correos'             => ['nullable', 'array', 'max:3'],
            'correos.*'           => ['nullable', 'string', 'email', 'max:100', 'distinct'],

            'telefonos'           => ['nullable', 'array', 'max:3'],
            'telefonos.*'         => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()\-]+$/', 'distinct'],
        ];
    }

    /** Reglas que dependen de más de un campo. */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                $this->validarDocumentoPorTipo($validador);
                $this->validarDigitosTelefono($validador);
            },
        ];
    }

    private function validarDocumentoPorTipo(Validator $validador): void
    {
        $documento = (string) $this->input('documento_identidad');
        $tipo = TipoDocumento::find($this->input('id_tipo_documento'));

        if (! $tipo || $documento === '') {
            return;
        }

        $codigo = (int) $tipo->codigo_sin;

        [$patron, $mensaje] = match ($codigo) {
            1 => ['/^\d{5,10}$/', 'La cédula de identidad debe tener solo números (de 5 a 10 dígitos).'],
            5 => ['/^\d{7,13}$/', 'El NIT debe tener solo números (de 7 a 13 dígitos).'],
            default => ['/^[A-Z0-9.\-]{3,20}$/', 'El documento solo admite letras, números, punto y guion (de 3 a 20 caracteres).'],
        };

        if (! $validador->errors()->has('documento_identidad') && ! preg_match($patron, $documento)) {
            $validador->errors()->add('documento_identidad', $mensaje);
        }

        if ($codigo !== 1 && filled($this->input('complemento'))) {
            $validador->errors()->add('complemento', 'El complemento solo se usa con la cédula de identidad.');
        }
    }

    private function validarDigitosTelefono(Validator $validador): void
    {
        foreach ((array) $this->input('telefonos', []) as $i => $telefono) {
            if (! is_string($telefono) || $telefono === '' || $validador->errors()->has("telefonos.$i")) {
                continue;
            }

            $digitos = strlen(preg_replace('/\D/', '', $telefono));

            if ($digitos < 7 || $digitos > 15) {
                $validador->errors()->add("telefonos.$i", 'El teléfono debe tener entre 7 y 15 dígitos.');
            }
        }
    }

    public function messages(): array
    {
        return [
            'id_tipo_documento.required' => 'Debes seleccionar el tipo de documento.',
            'id_tipo_documento.exists' => 'El tipo de documento seleccionado no es válido.',

            'documento_identidad.required' => 'El número de documento es obligatorio.',
            'documento_identidad.max' => 'El documento admite como máximo 20 caracteres.',
            'documento_identidad.unique' => 'Ya existe un cliente con este documento (puede estar dado de baja: revisa la Papelera).',

            'complemento.regex' => 'El complemento admite hasta 2 letras o números, por ejemplo 1A.',

            'razon_social.required' => 'La razón social o nombre es obligatorio.',
            'razon_social.min' => 'La razón social debe tener al menos 3 caracteres.',
            'razon_social.max' => 'La razón social admite como máximo 100 caracteres.',
            'razon_social.regex' => 'La razón social tiene caracteres no permitidos.',

            'nombre_responsable.min' => 'El responsable debe tener al menos 3 caracteres.',
            'nombre_responsable.max' => 'El responsable admite como máximo 100 caracteres.',
            'nombre_responsable.regex' => 'El responsable solo admite letras, espacios, punto, apóstrofo y guion.',

            'direccion.min' => 'La dirección debe tener al menos 5 caracteres.',
            'direccion.max' => 'La dirección admite como máximo 200 caracteres.',

            'correos.max' => 'Puedes registrar hasta 3 correos.',
            'correos.*.email' => 'Uno de los correos no tiene un formato válido.',
            'correos.*.max' => 'Un correo admite como máximo 100 caracteres.',
            'correos.*.distinct' => 'Hay un correo repetido.',

            'telefonos.max' => 'Puedes registrar hasta 3 teléfonos.',
            'telefonos.*.regex' => 'Un teléfono solo admite números, espacios, + ( ) y guion.',
            'telefonos.*.max' => 'Un teléfono admite como máximo 20 caracteres.',
            'telefonos.*.distinct' => 'Hay un teléfono repetido.',
        ];
    }
}
