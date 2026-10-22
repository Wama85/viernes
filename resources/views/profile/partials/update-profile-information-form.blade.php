<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Información del perfil
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Actualiza tu nombre. Tu correo es tu acceso al sistema y solo puede cambiarlo el administrador.
        </p>
    </header>

    <form method="POST" action="{{ route('profile.update') }}" class="mt-6" data-validar novalidate>
        @csrf
        @method('PATCH')

        <div data-resumen class="val-resumen" hidden></div>

        <x-campo-validado name="nombre" label="Nombre" :value="$usuario->nombre"
                          :obligatorio="true" :maxlength="100" :contador="true"
                          reglas="requerido|min:3|persona|max:100" autocomplete="name" />

        {{-- Correo: solo lectura --}}
        <div class="mb-4">
            <label for="correo_lectura" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Correo electrónico</label>
            <input id="correo_lectura" type="email" value="{{ $usuario->email }}" disabled
                   class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
        </div>

        {{-- Rol: solo lectura --}}
        <div class="mb-4">
            <label for="rol_lectura" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Rol</label>
            <input id="rol_lectura" type="text" value="{{ $usuario->rol->nombre_rol ?? '—' }}" disabled
                   class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Guardar</x-primary-button>

            @if (session('status') === 'perfil-actualizado')
                <p x-data="{ show: true }" x-show="show" x-transition
                   x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm text-green-700 dark:text-green-400">Guardado.</p>
            @endif
        </div>
    </form>
</section>
