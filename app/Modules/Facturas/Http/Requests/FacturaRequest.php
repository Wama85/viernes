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
        $conTarjeta = in_array($this->input('forma_pago'), Factura::FORMAS_CON_TARJETA, true);

        return [
            'forma_pago'          => ['required', Rule::in(Factura::FORMAS_PAGO)],
            'items'               => ['required', 'array', 'min:1'],
            'items.*'             => ['integer'],
            // Monto (5 u 8.99) o porcentaje (10%).
            'descuento_adicional' => ['nullable', 'string', 'max:12', 'regex:/^\s*\d+([.,]\d{1,2})?\s*%?\s*$/'],
            // Con tarjeta: primeros 4 y últimos 4 dígitos (el SIN recibe los 8 del medio en cero).
            'tarjeta_inicio'      => [Rule::requiredIf($conTarjeta), 'nullable', 'digits:4'],
            'tarjeta_fin'         => [Rule::requiredIf($conTarjeta), 'nullable', 'digits:4'],
            'confirmar_excepcion' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'forma_pago'          => 'forma de pago',
            'descuento_adicional' => 'descuento adicional',
            'tarjeta_inicio'      => 'primeros 4 dígitos de la tarjeta',
            'tarjeta_fin'         => 'últimos 4 dígitos de la tarjeta',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Selecciona al menos un ítem para la factura.',
            'items.min'      => 'Selecciona al menos un ítem para la factura.',
            'descuento_adicional.regex' => 'El descuento adicional debe ser un monto (por ejemplo 5 u 8.99) o un porcentaje (por ejemplo 10%).',
            'tarjeta_inicio.required'   => 'Escribe los 4 primeros dígitos de la tarjeta.',
            'tarjeta_fin.required'      => 'Escribe los 4 últimos dígitos de la tarjeta.',
            'tarjeta_inicio.digits'     => 'Los primeros dígitos de la tarjeta deben ser exactamente 4 números.',
            'tarjeta_fin.digits'        => 'Los últimos dígitos de la tarjeta deben ser exactamente 4 números.',
        ];
    }
}
