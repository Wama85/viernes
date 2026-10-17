<?php

namespace App\Modules\Papelera\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Papelera\Services\UsoRegistros;
use App\Modules\Productos\Models\Categoria;
use App\Modules\Productos\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PapeleraController extends Controller
{
    private const TIPOS = [
        'clientes'   => ['modelo' => Cliente::class,   'estado' => 'cliente_estado',   'titulo' => 'Clientes'],
        'productos'  => ['modelo' => Producto::class,  'estado' => 'producto_estado',  'titulo' => 'Productos'],
        'categorias' => ['modelo' => Categoria::class, 'estado' => 'categoria_estado', 'titulo' => 'Categorías'],
    ];

    public function __construct(private UsoRegistros $uso)
    {
    }

    public function index(Request $request)
    {
        $tipo = $this->tipo((string) $request->query('tipo', 'clientes'));
        $clase = self::TIPOS[$tipo]['modelo'];

        $conteos = [];
        foreach (self::TIPOS as $clave => $cfg) {
            $conteos[$clave] = $cfg['modelo']::where($cfg['estado'], 0)->count();
        }

        $registros = match ($tipo) {
            'clientes'   => Cliente::with('tipoDocumento')->where('cliente_estado', 0)->orderBy('razon_social')->get(),
            'productos'  => Producto::with('categoria')->where('producto_estado', 0)->orderBy('descripcion_producto')->get(),
            'categorias' => Categoria::where('categoria_estado', 0)->orderBy('nombre_categoria')->get(),
        };

        $modelo = new $clase;
        $ignorar = $this->tablasHijas($tipo);

        // Si no hay claves foráneas que comprobar, no se puede saber si algo se usa: se bloquea el borrado.
        $verificable = ! empty($this->uso->referencias($modelo, $ignorar));

        $usos = collect($this->uso->usos($modelo, $registros->modelKeys(), $ignorar))
            ->map(fn ($tablas) => collect($tablas)
                ->map(fn ($t) => $this->uso->etiqueta($t))
                ->unique()
                ->implode(', '))
            ->all();

        return view('papelera.index', [
            'tipos'       => self::TIPOS,
            'tipo'        => $tipo,
            'conteos'     => $conteos,
            'registros'   => $registros,
            'usos'        => $usos,
            'verificable' => $verificable,
        ]);
    }

    public function restaurar(string $tipo, int $id)
    {
        $tipo = $this->tipo($tipo);
        $cfg = self::TIPOS[$tipo];
        $registro = $cfg['modelo']::findOrFail($id);

        if ($tipo === 'productos' && $registro->categoria && (int) $registro->categoria->categoria_estado === 0) {
            return $this->volver($tipo)->with(
                'error',
                'La categoría «' . $registro->categoria->nombre_categoria . '» está dada de baja. Restáurala primero.'
            );
        }

        $registro->{$cfg['estado']} = 1;
        $registro->save();

        return $this->volver($tipo)->with('success', '«' . $this->nombre($tipo, $registro) . '» restaurado correctamente.');
    }

    public function eliminar(string $tipo, int $id)
    {
        $tipo = $this->tipo($tipo);
        $cfg = self::TIPOS[$tipo];
        $registro = $cfg['modelo']::findOrFail($id);
        $nombre = $this->nombre($tipo, $registro);

        if ((int) $registro->{$cfg['estado']} === 1) {
            return $this->volver($tipo)->with('error', 'Solo se puede eliminar lo que está en la papelera.');
        }

        $ignorar = $this->tablasHijas($tipo);

        if (empty($this->uso->referencias($registro, $ignorar))) {
            return $this->volver($tipo)->with(
                'error',
                'No se pudo comprobar si «' . $nombre . '» se usa en otros registros. No se eliminó.'
            );
        }

        $tablas = $this->uso->usos($registro, [$registro->getKey()], $ignorar)[(int) $registro->getKey()] ?? [];

        if (! empty($tablas)) {
            $donde = collect($tablas)->map(fn ($t) => $this->uso->etiqueta($t))->unique()->implode(', ');

            return $this->volver($tipo)->with(
                'error',
                '«' . $nombre . '» ya se usó en: ' . $donde . '. Solo puede quedar dado de baja.'
            );
        }

        $imagen = $tipo === 'productos' ? $registro->imagen : null;

        try {
            DB::transaction(function () use ($tipo, $registro) {
                if ($tipo === 'clientes') {
                    $registro->correos()->delete();
                    $registro->telefonos()->delete();
                }

                $registro->delete();
            });
        } catch (QueryException $e) {
            return $this->volver($tipo)->with('error', 'No se pudo eliminar «' . $nombre . '»: todavía tiene registros asociados.');
        }

        if ($imagen) {
            Storage::disk('public')->delete($imagen);
        }

        return $this->volver($tipo)->with('success', '«' . $nombre . '» eliminado definitivamente.');
    }

    private function tipo(string $tipo): string
    {
        abort_unless(array_key_exists($tipo, self::TIPOS), 404);

        return $tipo;
    }

    /** Tablas de datos hijos que se borran junto con el registro (no cuentan como "en uso"). */
    private function tablasHijas(string $tipo): array
    {
        if ($tipo !== 'clientes') {
            return [];
        }

        $cliente = new Cliente;

        return [
            $cliente->correos()->getRelated()->getTable(),
            $cliente->telefonos()->getRelated()->getTable(),
        ];
    }

    private function nombre(string $tipo, Model $registro): string
    {
        return match ($tipo) {
            'clientes'  => (string) $registro->razon_social,
            'productos' => (string) $registro->descripcion_producto,
            default     => (string) $registro->nombre_categoria,
        };
    }

    private function volver(string $tipo)
    {
        return redirect()->route('papelera.index', ['tipo' => $tipo]);
    }
}
