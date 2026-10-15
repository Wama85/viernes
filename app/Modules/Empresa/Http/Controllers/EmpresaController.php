<?php

namespace App\Modules\Empresa\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Empresa\Http\Requests\EmpresaRequest;
use App\Modules\Empresa\Models\Empresa;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    public function edit()
    {
        $empresa = Empresa::datos();

        return view('empresa.edit', compact('empresa'));
    }

    public function update(EmpresaRequest $request)
    {
        $empresa = Empresa::datos();
        $datos = $request->safe()->except(['logo', 'sello']);

        foreach (['logo', 'sello'] as $campo) {
            if ($request->hasFile($campo)) {
                if ($empresa->$campo) {
                    Storage::disk('public')->delete($empresa->$campo);
                }
                $datos[$campo] = $request->file($campo)->store('empresa', 'public');
            }
        }

        $empresa->fill($datos)->save();

        return redirect()->route('empresa.edit')
            ->with('success', 'Datos de la empresa guardados.');
    }
}
