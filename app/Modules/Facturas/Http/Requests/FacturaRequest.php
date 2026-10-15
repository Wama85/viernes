<?php

namespace App\Modules\Facturas\Http\Requests;

use App\Modules\Facturas\Models\Factura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'forma_pago' => ['required', Rule::in(Factura::FORMAS_PAGO)],
            'items'      => ['required', 'array', 'min:1'],
            'items.*'    => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return ['forma_pago' => 'forma de pago'];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Selecciona al menos un ítem para la factura.',
            'items.min'      => 'Selecciona al menos un ítem para la factura.',
        ];
    }
}
