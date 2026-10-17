<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Nuevo Cliente
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 p-6 shadow-sm sm:rounded-lg">

                <form method="POST" action="{{ route('clientes.store') }}" data-validar novalidate>
                    @csrf

                    <div data-resumen class="val-resumen" hidden></div>

                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Los campos marcados con <span class="text-red-600">*</span> son obligatorios.
                    </p>

                    {{-- Tipo de documento --}}
                    <div class="mb-4" data-campo>
                        <x-input-label for="id_tipo_documento">
                            Tipo de documento <span class="text-red-600">*</span>
                        </x-input-label>
                        <select id="id_tipo_documento" name="id_tipo_documento" data-reglas="requerido"
                                @if ($errors->first('id_tipo_documento')) data-error-servidor="{{ $errors->first('id_tipo_documento') }}" @endif
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Selecciona un tipo</option>
                            @foreach ($tiposDocumento as $tipo)
                                <option value="{{ $tipo->id_tipo_documento }}"
                                        data-codigo="{{ $tipo->codigo_sin }}"
                                        @selected(old('id_tipo_documento') == $tipo->id_tipo_documento)>
                                    {{ $tipo->nombre_tipo_documento }}
                                </option>
                            @endforeach
                        </select>
                        <div class="val-pie"><p data-mensaje class="val-msg" hidden></p></div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <x-campo-validado name="documento_identidad" label="Número de documento"
                                              :obligatorio="true" :mayusculas="true" :maxlength="20"
                                              reglas="requerido|documento|max:20" />
                        </div>
                        <div>
                            <x-campo-validado name="complemento" label="Complemento"
                                              :mayusculas="true" :maxlength="2"
                                              reglas="complemento" placeholder="No aplica" />
                        </div>
                    </div>

                    <x-campo-validado name="razon_social" label="Razón social / Nombre"
                                      :obligatorio="true" :mayusculas="true" :maxlength="100" :contador="true"
                                      reglas="requerido|min:3|razon|max:100" />

                    <x-campo-validado name="nombre_responsable" label="Responsable (opcional)"
                                      :mayusculas="true" :maxlength="100" :contador="true"
                                      reglas="min:3|persona|max:100" />

                    <x-lista-contactos nombre="correos" etiqueta="Correos electrónicos (opcional)"
                                       tipo="email" :valores="old('correos', [])"
                                       texto-agregar="+ Agregar otro correo"
                                       reglas="correo|max:100|unico" :maxlength="100"
                                       placeholder="nombre@dominio.com" />

                    <x-lista-contactos nombre="telefonos" etiqueta="Teléfonos (opcional)"
                                       tipo="text" :valores="old('telefonos', [])"
                                       texto-agregar="+ Agregar otro teléfono"
                                       reglas="telefono|unico" :maxlength="20"
                                       placeholder="Ej: 70000000" />

                    <x-campo-validado name="direccion" label="Dirección (opcional)"
                                      :maxlength="200" :contador="true"
                                      reglas="min:5|max:200" />

                    <div class="flex items-center justify-end mt-6 space-x-3">
                        <a href="{{ route('clientes.index') }}" class="text-gray-600 dark:text-gray-400">Cancelar</a>
                        <x-primary-button>Guardar</x-primary-button>
                    </div>
                </form>

                <x-validacion />
            </div>
        </div>
    </div>
</x-app-layout>
