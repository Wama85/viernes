<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Nuevo Usuario
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 p-6 shadow-sm sm:rounded-lg">

                <form method="POST" action="{{ route('usuarios.store') }}">
                    @csrf
<p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
    Los campos marcados con <span class="text-red-600">*</span> son obligatorios.
</p>
                    <div class="mb-4">
                        <x-input-label for="nombre">
    Nombre <span class="text-red-600">*</span>
</x-input-label>
                        <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                      value="{{ old('nombre') }}" required autofocus />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="email">
    Correo electrónico <span class="text-red-600">*</span>
</x-input-label>
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                      value="{{ old('email') }}" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="password">
    Contraseña <span class="text-red-600">*</span>
</x-input-label>
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="id_rol">
    Rol <span class="text-red-600">*</span>
</x-input-label>
                        <select id="id_rol" name="id_rol" required
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            <option value="">Selecciona un rol</option>
                            @foreach ($roles as $rol)
                                <option value="{{ $rol->id_rol }}" @selected(old('id_rol') == $rol->id_rol)>
                                    {{ $rol->nombre_rol }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('id_rol')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-6 space-x-3">
                        <a href="{{ route('usuarios.index') }}" class="text-gray-600 dark:text-gray-400">Cancelar</a>
                        <x-primary-button>Guardar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
