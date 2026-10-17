<?php

namespace App\Modules\Productos\Services;

use App\Modules\Productos\Models\MovimientoStock;
use App\Modules\Productos\Models\Producto;
use Illuminate\Support\Collection;

class StockService
{
    public const DOC_RECIBO  = 'RECIBO';
    public const DOC_FACTURA = 'FACTURA';

    /**
     * Descuenta el stock de los ítems de un documento.
     * Nunca deja el stock negativo: si no alcanza, descuenta lo que hay (queda en 0).
     * Devuelve la lista de productos que quedaron cortos.
     *
     * @param Collection $detalles  CotizacionDetalle con id_producto y cantidad
     */
    public function descontar(string $tipoDocumento, int $idDocumento, Collection $detalles, ?int $idUsuario): array
    {
        $faltantes = [];
        $nombreDoc = $tipoDocumento === self::DOC_RECIBO ? 'recibo' : 'factura';

        foreach ($detalles->groupBy('id_producto') as $idProducto => $lineas) {
            $producto = Producto::whereKey($idProducto)->lockForUpdate()->first();

            if (! $producto || ! $producto->controla_stock) {
                continue;
            }

            $pedido = round((float) $lineas->sum('cantidad'), 2);
            $disponible = max((float) $producto->stock, 0);
            $descontado = min($pedido, $disponible);

            if ($descontado < $pedido) {
                $faltantes[] = [
                    'producto'   => $producto->descripcion_producto,
                    'pedido'     => $pedido,
                    'descontado' => $descontado,
                ];
            }

            if ($descontado <= 0) {
                continue;
            }

            $this->mover($producto, MovimientoStock::SALIDA, -$descontado, $idUsuario, [
                'tipo_documento' => $tipoDocumento,
                'id_documento'   => $idDocumento,
                'motivo'         => "Salida por {$nombreDoc}",
            ]);
        }

        return $faltantes;
    }

    /** Devuelve al stock lo que realmente se había descontado por un documento. */
    public function devolver(string $tipoDocumento, int $idDocumento, ?int $idUsuario, string $motivo): void
    {
        $salidas = MovimientoStock::where('tipo_documento', $tipoDocumento)
            ->where('id_documento', $idDocumento)
            ->where('tipo', MovimientoStock::SALIDA)
            ->where('revertido', false)
            ->lockForUpdate()
            ->get();

        foreach ($salidas as $salida) {
            $producto = Producto::whereKey($salida->id_producto)->lockForUpdate()->first();

            if ($producto) {
                $this->mover($producto, MovimientoStock::DEVOLUCION, abs($salida->cantidad), $idUsuario, [
                    'tipo_documento' => $tipoDocumento,
                    'id_documento'   => $idDocumento,
                    'motivo'         => $motivo,
                ]);
            }

            $salida->update(['revertido' => true]);
        }
    }

    public function entrada(Producto $producto, float $cantidad, ?int $idUsuario, ?string $motivo = null): void
    {
        $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();

        $this->mover($bloqueado, MovimientoStock::ENTRADA, $cantidad, $idUsuario, [
            'motivo' => $motivo ?: 'Ingreso de mercadería',
        ]);
    }

    public function stockInicial(Producto $producto, float $cantidad, ?int $idUsuario): void
    {
        $this->entrada($producto, $cantidad, $idUsuario, 'Stock inicial');
    }

    /** Fija el stock al valor contado realmente. */
    public function ajustar(Producto $producto, float $stockReal, ?int $idUsuario, string $motivo): void
    {
        $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
        $diferencia = round($stockReal - (float) $bloqueado->stock, 2);

        if ($diferencia == 0.0) {
            return;
        }

        $this->mover($bloqueado, MovimientoStock::AJUSTE, $diferencia, $idUsuario, ['motivo' => $motivo]);
    }

    public function mensajeFaltantes(array $faltantes): string
    {
        if (empty($faltantes)) {
            return '';
        }

        $partes = array_map(
            fn ($f) => sprintf(
                '%s: se descontaron %s de %s (stock en 0)',
                $f['producto'],
                $this->numero($f['descontado']),
                $this->numero($f['pedido'])
            ),
            $faltantes
        );

        return ' Aviso de stock — ' . implode('; ', $partes) . '.';
    }

    // ------------------------------------------------------------------

    private function mover(Producto $producto, string $tipo, float $delta, ?int $idUsuario, array $extra = []): MovimientoStock
    {
        $antes = (float) $producto->stock;
        $despues = round($antes + $delta, 2);

        $producto->stock = $despues;
        $producto->save();

        return MovimientoStock::create([
            'id_producto'     => $producto->id_producto,
            'tipo'            => $tipo,
            'cantidad'        => round($delta, 2),
            'stock_anterior'  => $antes,
            'stock_posterior' => $despues,
            'tipo_documento'  => $extra['tipo_documento'] ?? null,
            'id_documento'    => $extra['id_documento'] ?? null,
            'motivo'          => $extra['motivo'] ?? null,
            'id_usuario'      => $idUsuario,
            'fecha_hora'      => now(),
        ]);
    }

    private function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
