<?php

namespace App\Modules\Cotizaciones\Http\Requests;

use App\Modules\Cotizaciones\Models\Cotizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CotizacionRequest extends FormRequest
{
    private const MONTO_MAXIMO = '9999999.99';
    private const CANTIDAD_MAXIMA = '99999.99';

    /** Sin caracteres de control ni < >. Se permiten tabulaciones y saltos de línea. */
    private const TEXTO_SEGURO = '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]+$/u';

    public function authorize(): bool
    {
        return true;
    }

    /** Limpia los datos: espacios, saltos de línea y números con coma decimal. */
    protected function prepareForValidation(): void
    {
        $linea = function ($valor) {
            if (! is_string($valor)) {
                return $valor;
            }
            $valor = trim(preg_replace('/\s+/u', ' ', $valor));

            return $valor === '' ? null : $valor;
        };

        $numero = function ($valor) {
            if (! is_string($valor)) {
                return $valor;
            }
            $valor = str_replace(',', '.', preg_replace('/\s+/', '', $valor));

            return $valor === '' ? null : $valor;
        };

        $observaciones = $this->input('observaciones');
        if (is_string($observaciones)) {
            $observaciones = trim(str_replace(["\r\n", "\r"], "\n", $observaciones));
            $observaciones = $observaciones === '' ? null : $observaciones;
        }

        $cambios = ['observaciones' => $observaciones];

        $vigencia = $this->input('vigencia_cantidad');
        if (is_string($vigencia)) {
            $cambios['vigencia_cantidad'] = $linea($vigencia);
        }

        $items = $this->input('items');
        if (is_array($items)) {
            $cambios['items'] = array_map(function ($item) use ($numero, $linea) {
                if (! is_array($item)) {
                    return $item;
                }
                foreach (['cantidad', 'precio_unitario', 'descuento'] as $campo) {
                    if (array_key_exists($campo, $item)) {
                        $item[$campo] = $numero($item[$campo]);
                    }
                }
                if (array_key_exists('descripcion_adicional', $item)) {
                    $item['descripcion_adicional'] = $linea($item['descripcion_adicional']);
                }

                return $item;
            }, $items);
        }

        $this->merge($cambios);
    }

    public function rules(): array
    {
        $esCreacion = $this->isMethod('post');

        $cotizacion = $this->route('cotizacion');
        $actual = $cotizacion instanceof Cotizacion ? $cotizacion : null;

        // Con recibos activos, los ítems quedan bloqueados y no se validan.
        $itemsBloqueados = $actual && $actual->tieneRecibos();

        $reglas = [
            // El cliente debe estar activo, salvo el que la cotización ya tiene al editarla.
            'id_cliente' => ['required', Rule::exists('clientes', 'id_cliente')->where(function ($consulta) use ($actual) {
                $consulta->where(function ($grupo) use ($actual) {
                    $grupo->where('cliente_estado', 1);
                    if ($actual) {
                        $grupo->orWhere('id_cliente', $actual->id_cliente);
                    }
                });
            })],

            // La vigencia es obligatoria al crear; al editar es opcional.
            'vigencia_cantidad' => $esCreacion
                ? ['bail', 'required', 'integer', 'min:1', 'max:365']
                : ['bail', 'nullable', 'integer', 'min:1', 'max:365'],
            'vigencia_unidad' => $esCreacion
                ? ['required', Rule::in(['HORAS', 'DIAS'])]
                : ['nullable', 'required_with:vigencia_cantidad', Rule::in(['HORAS', 'DIAS'])],

            'observaciones'   => ['nullable', 'string', 'max:1000', 'regex:' . self::TEXTO_SEGURO],
            'confirmar_stock' => ['nullable', 'boolean'],
        ];

        if (! $itemsBloqueados) {
            // Productos activos, más los que la cotización ya usa al editarla.
            $idsActuales = $actual ? $actual->detalles()->pluck('id_producto')->all() : [];

            $reglas += [
                'items'                         => ['required', 'array', 'min:1', 'max:100'],
                'items.*.id_producto'           => ['required', Rule::exists('productos', 'id_producto')->where(function ($consulta) use ($idsActuales) {
                    $consulta->where(function ($grupo) use ($idsActuales) {
                        $grupo->where('producto_estado', 1);
                        if ($idsActuales) {
                            $grupo->orWhereIn('id_producto', $idsActuales);
                        }
                    });
                })],
                'items.*.cantidad'              => $this->numero(true, '0.01', self::CANTIDAD_MAXIMA),
                'items.*.precio_unitario'       => $this->numero(true, '0', self::MONTO_MAXIMO),
                'items.*.descuento'             => $this->numero(false, '0', self::MONTO_MAXIMO),
                'items.*.descripcion_adicional' => ['nullable', 'string', 'max:300', 'regex:' . self::TEXTO_SEGURO],
            ];
        }

        return $reglas;
    }

    /** Número con hasta 2 decimales, sin signo, con mínimo y tope. */
    private function numero(bool $obligatorio, string $minimo, string $maximo): array
    {
        return [
            'bail',
            $obligatorio ? 'required' : 'nullable',
            'regex:/^\d+(\.\d{1,2})?$/',
            'numeric',
            'min:' . $minimo,
            'max:' . $maximo,
        ];
    }

    public function messages(): array
    {
        $formato = 'Escribe un número con hasta 2 decimales, por ejemplo 12.50.';

        return [
            'id_cliente.required' => 'Debe seleccionar un cliente.',
            'id_cliente.exists'   => 'El cliente seleccionado no existe o está dado de baja.',

            'vigencia_cantidad.required' => 'Debe indicar la vigencia.',
            'vigencia_cantidad.integer'  => 'La vigencia debe ser un número entero.',
            'vigencia_cantidad.min'      => 'La vigencia mínima es 1.',
            'vigencia_cantidad.max'      => 'La vigencia máxima es 365.',
            'vigencia_unidad.required'      => 'Debe indicar la unidad de la vigencia.',
            'vigencia_unidad.required_with' => 'Debe indicar la unidad de la vigencia.',
            'vigencia_unidad.in'            => 'La unidad de la vigencia no es válida.',

            'observaciones.max'   => 'Las observaciones admiten como máximo 1000 caracteres.',
            'observaciones.regex' => 'Las observaciones tienen caracteres no permitidos (por ejemplo < o >).',

            'items.required' => 'Debe agregar al menos un producto o servicio.',
            'items.min'      => 'Debe agregar al menos un producto o servicio.',
            'items.max'      => 'Una cotización admite como máximo 100 líneas.',

            'items.*.id_producto.required' => 'Seleccione un producto en cada línea.',
            'items.*.id_producto.exists'   => 'Un producto seleccionado no existe o está dado de baja.',

            'items.*.cantidad.required' => 'Indique la cantidad en cada línea.',
            'items.*.cantidad.regex'    => $formato,
            'items.*.cantidad.numeric'  => $formato,
            'items.*.cantidad.min'      => 'La cantidad debe ser mayor a cero.',
            'items.*.cantidad.max'      => 'La cantidad máxima por línea es 99,999.99.',

            'items.*.precio_unitario.required' => 'Indique el precio en cada línea.',
            'items.*.precio_unitario.regex'    => $formato,
            'items.*.precio_unitario.numeric'  => $formato,
            'items.*.precio_unitario.min'      => 'El precio no puede ser negativo.',
            'items.*.precio_unitario.max'      => 'El precio máximo es 9,999,999.99.',

            'items.*.descuento.regex'   => $formato,
            'items.*.descuento.numeric' => $formato,
            'items.*.descuento.min'     => 'El descuento no puede ser negativo.',
            'items.*.descuento.max'     => 'El descuento máximo es 9,999,999.99.',

            'items.*.descripcion_adicional.max'   => 'La descripción adicional admite como máximo 300 caracteres.',
            'items.*.descripcion_adicional.regex' => 'La descripción adicional tiene caracteres no permitidos (por ejemplo < o >).',
        ];
    }
}
