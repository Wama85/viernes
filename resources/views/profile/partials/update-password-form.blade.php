<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Cambiar contraseña
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Usa una contraseña larga y difícil de adivinar, con letras y números.
        </p>
    </header>

    <form method="POST" action="{{ route('password.update') }}" class="mt-6" data-validar novalidate>
        @csrf
        @method('PUT')

        <div data-resumen class="val-resumen" hidden></div>

        <x-campo-clave name="current_password" label="Contraseña actual"
                       :obligatorio="true"
                       reglas="requerido"
                       autocomplete="current-password" />

        <x-campo-clave name="password" label="Nueva contraseña"
                       :obligatorio="true" :medidor="true"
                       reglas="requerido|clave"
                       ayuda="Mínimo 8 caracteres, con letras y números." />

        <x-campo-clave name="password_confirmation" label="Confirmar nueva contraseña"
                       :obligatorio="true"
                       reglas="requerido|igual:password" />

        <div class="flex items-center gap-4">
            <x-primary-button>Cambiar contraseña</x-primary-button>

            @if (session('status') === 'clave-actualizada')
                <p x-data="{ show: true }" x-show="show" x-transition
                   x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm text-green-700 dark:text-green-400">Contraseña actualizada.</p>
            @endif
        </div>
    </form>
</section>
