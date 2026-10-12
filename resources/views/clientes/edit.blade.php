<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Editar Cliente
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 p-6 shadow-sm sm:rounded-lg">

                <form method="POST" action="{{ route('clientes.update', $cliente) }}">
                    @csrf
                    @method('PUT')
<p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
    Los campos marcados con <span class="text-red-600">*</span> son obligatorios.
</p>
                    <div class="mb-4">
                        <x-input-label for="id_tipo_documento">
    Tipo de documento <span class="text-red-600">*</span>
</x-input-label>
                        <select id="id_tipo_documento" name="id_tipo_documento"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach ($tiposDocumento as $tipo)
                                <option value="{{ $tipo->id_tipo_documento }}"
                                        @selected(old('id_tipo_documento', $cliente->id_tipo_documento) == $tipo->id_tipo_documento)>
                                    {{ $tipo->nombre_tipo_documento }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('id_tipo_documento')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="col-span-2">
                            <x-input-label for="documento_identidad">
    Número de documento <span class="text-red-600">*</span>
</x-input-label>
                            <x-text-input id="documento_identidad" name="documento_identidad" type="text"
                                          class="mt-1 block w-full"
                                          value="{{ old('documento_identidad', $cliente->documento_identidad) }}" required />
                            <x-input-error :messages="$errors->get('documento_identidad')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="complemento" value="Complemento" />
                            <x-text-input id="complemento" name="complemento" type="text"
                                          class="mt-1 block w-full uppercase"
                                          value="{{ old('complemento', $cliente->complemento) }}" />
                            <x-input-error :messages="$errors->get('complemento')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mb-4">
                        <x-input-label for="razon_social">
    Razón social / Nombre <span class="text-red-600">*</span>
</x-input-label>
                        <x-text-input id="razon_social" name="razon_social" type="text"
                                      class="mt-1 block w-full uppercase"
                                      value="{{ old('razon_social', $cliente->razon_social) }}" required />
                        <x-input-error :messages="$errors->get('razon_social')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="nombre_responsable" value="Responsable (opcional)" />
                        <x-text-input id="nombre_responsable" name="nombre_responsable" type="text"
                                      class="mt-1 block w-full uppercase"
                                      value="{{ old('nombre_responsable', $cliente->nombre_responsable) }}" />
                        <x-input-error :messages="$errors->get('nombre_responsable')" class="mt-2" />
                    </div>

                    <x-lista-dinamica nombre="correos" etiqueta="Correos electrónicos (opcional)"
                                      tipo="email"
                                      :valores="old('correos', $cliente->correos->pluck('correo')->all())"
                                      texto-agregar="+ Agregar otro correo" />

                    <x-lista-dinamica nombre="telefonos" etiqueta="Teléfonos (opcional)"
                                      tipo="text"
                                      :valores="old('telefonos', $cliente->telefonos->pluck('telefono')->all())"
                                      texto-agregar="+ Agregar otro teléfono" />

                    <div class="mb-4">
                        <x-input-label for="direccion" value="Dirección (opcional)" />
                        <x-text-input id="direccion" name="direccion" type="text"
                                      class="mt-1 block w-full"
                                      value="{{ old('direccion', $cliente->direccion) }}" />
                        <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-6 space-x-3">
                        <a href="{{ route('clientes.index') }}" class="text-gray-600 dark:text-gray-400">Cancelar</a>
                        <x-primary-button>Actualizar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
