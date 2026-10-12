<?php

namespace App\Modules\Clientes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Http\Requests\StoreClienteRequest;
use App\Modules\Clientes\Http\Requests\UpdateClienteRequest;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\TipoDocumento;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    public function index()
    {
        $clientes = Cliente::with(['tipoDocumento', 'correos', 'telefonos'])
            ->orderBy('razon_social')
            ->get();

        return view('clientes.index', compact('clientes'));
    }

    public function create()
    {
        $tiposDocumento = TipoDocumento::where('tipo_documento_estado', 1)->get();

        return view('clientes.create', compact('tiposDocumento'));
    }

    public function store(StoreClienteRequest $request)
    {
        $datos = $request->validated();

        DB::transaction(function () use ($datos) {
            $cliente = Cliente::create(
                Arr::except($datos, ['correos', 'telefonos']) + ['cliente_estado' => 1]
            );

            $this->guardarContactos($cliente, $datos);
        });

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente registrado correctamente.');
    }

    public function edit(Cliente $cliente)
    {
        $cliente->load(['correos', 'telefonos']);
        $tiposDocumento = TipoDocumento::where('tipo_documento_estado', 1)->get();

        return view('clientes.edit', compact('cliente', 'tiposDocumento'));
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente)
    {
        $datos = $request->validated();

        DB::transaction(function () use ($datos, $cliente) {
            $cliente->update(Arr::except($datos, ['correos', 'telefonos']));

            $this->guardarContactos($cliente, $datos);
        });

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function cambiarEstado(Cliente $cliente)
    {
        $cliente->cliente_estado = $cliente->cliente_estado == 1 ? 0 : 1;
        $cliente->save();

        $mensaje = $cliente->cliente_estado == 1
            ? 'Cliente reactivado correctamente.'
            : 'Cliente dado de baja correctamente.';

        return redirect()->route('clientes.index')->with('success', $mensaje);
    }

    private function guardarContactos(Cliente $cliente, array $datos): void
    {
        $cliente->correos()->delete();
        $cliente->telefonos()->delete();

        foreach (array_filter($datos['correos'] ?? []) as $correo) {
            $cliente->correos()->create(['correo' => $correo]);
        }

        foreach (array_filter($datos['telefonos'] ?? []) as $telefono) {
            $cliente->telefonos()->create(['telefono' => $telefono]);
        }
    }
}
