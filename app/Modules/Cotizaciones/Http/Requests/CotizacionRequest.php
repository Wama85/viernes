<?php

namespace App\Modules\Cotizaciones\Http\Requests;

use App\Modules\Cotizaciones\Models\Cotizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // La vigencia es obligatoria al crear; al editar es opcional.
        $vigencia = $this->isMethod('post') ? 'required' : 'nullable';

        // Con recibos activos, los ítems quedan bloqueados y no se validan.
        $cotizacion = $this->route('cotizacion');
        $itemsBloqueados = $cotizacion instanceof Cotizacion && $cotizacion->tieneRecibos();

        $reglas = [
            'id_cliente' => ['required', Rule::exists('clientes', 'id_cliente')->where('cliente_estado', 1)],
            'vigencia_cantidad' => [$vigencia, 'integer', 'min:1', 'max:365'],
            'vigencia_unidad' => [$vigencia, Rule::in(['HORAS', 'DIAS'])],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'confirmar_stock' => ['nullable', 'boolean'],
        ];

        if (! $itemsBloqueados) {
            $reglas += [
                'items' => ['required', 'array', 'min:1'],
                'items.*.id_producto' => ['required', Rule::exists('productos', 'id_producto')->where('producto_estado', 1)],
                'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
                'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
                'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
                'items.*.descripcion_adicional' => ['nullable', 'string', 'max:1000'],
            ];
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            'id_cliente.required' => 'Debe seleccionar un cliente.',
            'id_cliente.exists' => 'El cliente seleccionado no existe o está dado de baja.',
            'vigencia_cantidad.required' => 'Debe indicar la vigencia.',
            'items.required' => 'Debe agregar al menos un producto o servicio.',
            'items.min' => 'Debe agregar al menos un producto o servicio.',
            'items.*.id_producto.required' => 'Seleccione un producto en cada línea.',
            'items.*.id_producto.exists' => 'Un producto seleccionado no existe o está dado de baja.',
            'items.*.cantidad.required' => 'Indique la cantidad en cada línea.',
            'items.*.cantidad.gt' => 'La cantidad debe ser mayor a cero.',
            'items.*.precio_unitario.required' => 'Indique el precio en cada línea.',
        ];
    }
}
