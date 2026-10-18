<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Editar Usuario
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 p-6 shadow-sm sm:rounded-lg">

                <form method="POST" action="{{ route('usuarios.update', $usuario) }}" data-validar novalidate>
                    @csrf
                    @method('PUT')

                    <div data-resumen class="val-resumen" hidden></div>

                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Los campos marcados con <span class="text-red-600">*</span> son obligatorios.
                    </p>

                    <x-campo-validado name="nombre" label="Nombre"
                                      :value="$usuario->nombre"
                                      :obligatorio="true" :maxlength="100" :contador="true"
                                      reglas="requerido|min:3|persona|max:100" autofocus />

                    <x-campo-validado name="email" type="email" label="Correo electrónico"
                                      :value="$usuario->email"
                                      :obligatorio="true" :maxlength="100"
                                      reglas="requerido|correo|max:100"
                                      placeholder="nombre@dominio.com" inputmode="email" />

                    <x-campo-clave name="password" label="Nueva contraseña (opcional)"
                                   :medidor="true"
                                   reglas="clave"
                                   ayuda="Déjala en blanco para mantener la contraseña actual." />

                    <x-campo-clave name="password_confirmation" label="Confirmar nueva contraseña"
                                   reglas="igual:password" />

                    {{-- Rol --}}
                    <div class="mb-4" data-campo>
                        <x-input-label for="id_rol">
                            Rol <span class="text-red-600">*</span>
                        </x-input-label>
                        <select id="id_rol" name="id_rol" data-reglas="requerido"
                                @if ($errors->first('id_rol')) data-error-servidor="{{ $errors->first('id_rol') }}" @endif
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($roles as $rol)
                                <option value="{{ $rol->id_rol }}" @selected(old('id_rol', $usuario->id_rol) == $rol->id_rol)>
                                    {{ $rol->nombre_rol }}
                                </option>
                            @endforeach
                        </select>
                        <div class="val-pie"><p data-mensaje class="val-msg" hidden></p></div>
                    </div>

                    <div class="flex items-center justify-end mt-6 space-x-3">
                        <a href="{{ route('usuarios.index') }}" class="text-gray-600 dark:text-gray-400">Cancelar</a>
                        <x-primary-button>Actualizar</x-primary-button>
                    </div>
                </form>

                <x-validacion />
            </div>
        </div>
    </div>
</x-app-layout>
