<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Datos de la empresa</h2>
    </x-slot>

    @php
        $inputClase = 'block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm';
        $etiqueta = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    @endphp

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Estos datos aparecen en los PDF de cotizaciones y recibos.
                    Los campos marcados con <span class="text-red-600 font-bold">*</span> son obligatorios.
                </p>

                <form method="POST" action="{{ route('empresa.update') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    @method('PUT')

                    @if ($errors->any())
                        <div class="p-3 rounded bg-red-100 text-red-800 text-sm">
                            <ul class="list-disc ml-5">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <div>
                        <label class="{{ $etiqueta }}">Nombre de la empresa <span class="text-red-600">*</span></label>
                        <input type="text" name="nombre_empresa" maxlength="150" required
                               value="{{ old('nombre_empresa', $empresa->nombre_empresa) }}" class="{{ $inputClase }} uppercase">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="{{ $etiqueta }}">Propietario <span class="text-red-600">*</span></label>
                            <input type="text" name="propietario" maxlength="100" required
                                   value="{{ old('propietario', $empresa->propietario) }}" class="{{ $inputClase }} uppercase">
                        </div>
                        <div>
                            <label class="{{ $etiqueta }}">Carnet <span class="text-red-600">*</span></label>
                            <input type="text" name="carnet" maxlength="20" required
                                   value="{{ old('carnet', $empresa->carnet) }}" class="{{ $inputClase }}">
                        </div>
                    </div>

                    <div>
                        <label class="{{ $etiqueta }}">NIT</label>
                        <input type="text" name="nit" maxlength="20" value="{{ old('nit', $empresa->nit) }}" class="{{ $inputClase }}">
                    </div>

                    <div>
                        <label class="{{ $etiqueta }}">Dirección <span class="text-red-600">*</span></label>
                        <input type="text" name="direccion" maxlength="255" required
                               value="{{ old('direccion', $empresa->direccion) }}" class="{{ $inputClase }}">
                    </div>

                    <div>
                        <label class="{{ $etiqueta }}">Ciudad y país</label>
                        <input type="text" name="ciudad_pais" maxlength="100"
                               value="{{ old('ciudad_pais', $empresa->ciudad_pais) }}" class="{{ $inputClase }}">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $etiqueta }}">Teléfonos <span class="text-red-600">*</span></label>
                            <input type="text" name="telefonos" maxlength="100" required
                                   value="{{ old('telefonos', $empresa->telefonos) }}" class="{{ $inputClase }}">
                        </div>
                        <div>
                            <label class="{{ $etiqueta }}">Correo <span class="text-red-600">*</span></label>
                            <input type="email" name="correo" maxlength="100" required
                                   value="{{ old('correo', $empresa->correo) }}" class="{{ $inputClase }}">
                        </div>
                    </div>

                    <div>
                        <label class="{{ $etiqueta }}">Sitio web</label>
                        <input type="text" name="sitio_web" maxlength="100"
                               value="{{ old('sitio_web', $empresa->sitio_web) }}" class="{{ $inputClase }}">
                    </div>

                    <div>
                        <label class="{{ $etiqueta }}">Formas de pago (texto que sale en la cotización)</label>
                        <input type="text" name="formas_pago" maxlength="255"
                               value="{{ old('formas_pago', $empresa->formas_pago) }}" class="{{ $inputClase }}">
                    </div>

                    <div>
                        <label class="{{ $etiqueta }}">Cuentas bancarias (una por línea)</label>
                        <textarea name="cuentas_bancarias" rows="3" class="{{ $inputClase }}">{{ old('cuentas_bancarias', $empresa->cuentas_bancarias) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $etiqueta }}">Logo</label>
                            @if ($empresa->logo)
                                <img src="{{ asset('storage/' . $empresa->logo) }}" alt="Logo actual" class="h-20 my-2">
                            @endif
                            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"
                                   class="block w-full text-sm text-gray-700 dark:text-gray-300">
                        </div>
                        <div>
                            <label class="{{ $etiqueta }}">Sello</label>
                            @if ($empresa->sello)
                                <img src="{{ asset('storage/' . $empresa->sello) }}" alt="Sello actual" class="h-20 my-2">
                            @endif
                            <input type="file" name="sello" accept=".jpg,.jpeg,.png,.webp"
                                   class="block w-full text-sm text-gray-700 dark:text-gray-300">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">JPG, PNG o WEBP, máximo 2 MB. Se recomienda PNG con fondo transparente.</p>

                    <div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
