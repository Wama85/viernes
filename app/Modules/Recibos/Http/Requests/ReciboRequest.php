<?php

namespace App\Modules\Recibos\Http\Requests;

use App\Modules\Recibos\Models\Recibo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReciboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'forma_pago'      => ['required', Rule::in(Recibo::FORMAS_PAGO)],
            'referencia_pago' => ['nullable', 'string', 'max:100'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*'         => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'forma_pago'      => 'forma de pago',
            'referencia_pago' => 'referencia',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Selecciona al menos un ítem para el recibo.',
            'items.min'      => 'Selecciona al menos un ítem para el recibo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'referencia_pago' => $this->filled('referencia_pago')
                ? mb_strtoupper(trim($this->referencia_pago))
                : null,
        ]);
    }
}
