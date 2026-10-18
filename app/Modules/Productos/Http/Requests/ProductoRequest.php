<?php

namespace App\Modules\Productos\Http\Requests;

use App\Modules\Productos\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductoRequest extends FormRequest
{
    /** Máximo que admite una columna decimal(10,2) con margen. */
    private const MONTO_MAXIMO = '9999999.99';

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

        $detalle = $this->input('detalle');
        if (is_string($detalle)) {
            $detalle = trim(str_replace(["\r\n", "\r"], "\n", $detalle));
            $detalle = $detalle === '' ? null : $detalle;
        }

        $this->merge([
            'descripcion_producto' => $linea($this->input('descripcion_producto')),
            'detalle'              => $detalle,
            'costo_compra'         => $numero($this->input('costo_compra')),
            'precio_venta'         => $numero($this->input('precio_venta')),
            'stock'                => $numero($this->input('stock')),
            'stock_minimo'         => $numero($this->input('stock_minimo')),
        ]);
    }

    public function rules(): array
    {
        $producto = $this->route('producto');

        return [
            'id_producto_sin'  => ['required', $this->existeActivo('productos_sin', 'id_producto_sin', 'producto_sin_estado', $producto?->id_producto_sin)],
            'id_categoria'     => ['required', $this->existeActivo('categorias', 'id_categoria', 'categoria_estado', $producto?->id_categoria)],
            'id_unidad_medida' => ['required', $this->existeActivo('unidades_medida', 'id_unidad_medida', 'unidad_medida_estado', $producto?->id_unidad_medida)],

            'descripcion_producto' => ['required', 'string', 'min:3', 'max:200', 'regex:' . self::TEXTO_SEGURO],
            'detalle'              => ['nullable', 'string', 'max:1000', 'regex:' . self::TEXTO_SEGURO],

            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'costo_compra' => $this->monto(false),
            'precio_venta' => $this->monto(true, '0.01'),

            'controla_stock' => ['nullable', 'boolean'],

            // Si el producto no controla stock (servicios), estos campos se ignoran.
            'stock'             => array_merge(['exclude_if:controla_stock,0'], $this->monto(false)),
            'stock_minimo'      => array_merge(['exclude_if:controla_stock,0'], $this->monto(false)),
            'fecha_vencimiento' => [
                'exclude_if:controla_stock,0', 'nullable', 'date',
                'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31',
            ],
        ];
    }

    /** No se repite la misma descripción con el mismo detalle dentro de una categoría. */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                if ($validador->errors()->hasAny(['descripcion_producto', 'id_categoria', 'detalle'])) {
                    return;
                }

                $actual = $this->route('producto');
                $detalle = $this->input('detalle');

                $repetido = Producto::where('id_categoria', $this->input('id_categoria'))
                    ->where('descripcion_producto', $this->input('descripcion_producto'))
                    ->when(
                        $detalle !== null,
                        fn ($q) => $q->where('detalle', $detalle),
                        fn ($q) => $q->where(fn ($w) => $w->whereNull('detalle')->orWhere('detalle', ''))
                    )
                    ->when($actual, fn ($q) => $q->where('id_producto', '!=', $actual->id_producto))
                    ->exists();

                if ($repetido) {
                    $validador->errors()->add(
                        'descripcion_producto',
                        'Ya existe un producto igual en esta categoría (puede estar dado de baja: revisa la Papelera). '
                        . 'Si es distinto, diferéncialo en la descripción o en el detalle.'
                    );
                }
            },
        ];
    }

    /** El registro debe estar activo, salvo el que el producto ya tiene al editarlo. */
    private function existeActivo(string $tabla, string $clave, string $estado, $actual)
    {
        return Rule::exists($tabla, $clave)->where(function ($consulta) use ($clave, $estado, $actual) {
            $consulta->where(function ($grupo) use ($clave, $estado, $actual) {
                $grupo->where($estado, 1);
                if ($actual) {
                    $grupo->orWhere($clave, $actual);
                }
            });
        });
    }

    /** Número con hasta 2 decimales, sin signo, con tope. */
    private function monto(bool $obligatorio, string $minimo = '0'): array
    {
        return [
            'bail',
            $obligatorio ? 'required' : 'nullable',
            'regex:/^\d+(\.\d{1,2})?$/',
            'numeric',
            'min:' . $minimo,
            'max:' . self::MONTO_MAXIMO,
        ];
    }

    public function messages(): array
    {
        $formato = 'Escribe un número con hasta 2 decimales, por ejemplo 12.50.';
        $maximo = 'El valor máximo permitido es 9,999,999.99.';

        return [
            'id_producto_sin.required' => 'Debes seleccionar el producto del SIN.',
            'id_producto_sin.exists'   => 'El producto del SIN seleccionado no es válido.',
            'id_categoria.required'    => 'Debes seleccionar una categoría.',
            'id_categoria.exists'      => 'La categoría seleccionada no es válida o está dada de baja.',
            'id_unidad_medida.required' => 'Debes seleccionar la unidad de medida.',
            'id_unidad_medida.exists'   => 'La unidad de medida seleccionada no es válida o está dada de baja.',

            'descripcion_producto.required' => 'La descripción del producto es obligatoria.',
            'descripcion_producto.min'      => 'La descripción debe tener al menos 3 caracteres.',
            'descripcion_producto.max'      => 'La descripción admite como máximo 200 caracteres.',
            'descripcion_producto.regex'    => 'La descripción tiene caracteres no permitidos (por ejemplo < o >).',

            'detalle.max'   => 'El detalle admite como máximo 1000 caracteres.',
            'detalle.regex' => 'El detalle tiene caracteres no permitidos (por ejemplo < o >).',

            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'   => 'La imagen no puede pesar más de 2 MB.',

            'costo_compra.regex'   => $formato,
            'costo_compra.numeric' => $formato,
            'costo_compra.min'     => 'El costo no puede ser negativo.',
            'costo_compra.max'     => $maximo,

            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'precio_venta.regex'    => $formato,
            'precio_venta.numeric'  => $formato,
            'precio_venta.min'      => 'El precio de venta debe ser mayor que cero.',
            'precio_venta.max'      => $maximo,

            'stock.regex'   => $formato,
            'stock.numeric' => $formato,
            'stock.min'     => 'El stock no puede ser negativo.',
            'stock.max'     => $maximo,

            'stock_minimo.regex'   => $formato,
            'stock_minimo.numeric' => $formato,
            'stock_minimo.min'     => 'El stock mínimo no puede ser negativo.',
            'stock_minimo.max'     => $maximo,

            'fecha_vencimiento.date'                => 'Escribe una fecha válida.',
            'fecha_vencimiento.after_or_equal'      => 'El año debe estar entre 2000 y 2100.',
            'fecha_vencimiento.before_or_equal'     => 'El año debe estar entre 2000 y 2100.',
        ];
    }
}
