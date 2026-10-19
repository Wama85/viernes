<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Nueva contraseña</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Mínimo 8 caracteres, con al menos una letra y un número.
        </p>
    </div>

    <form method="POST" action="{{ route('password.actualizar') }}">
        @csrf

        <div>
            <x-input-label for="password" value="Nueva contraseña" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password"
                          required autofocus maxlength="72" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirmar contraseña" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation"
                          required maxlength="72" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-3">
            <label class="inline-flex items-center">
                <input type="checkbox" class="rounded border-gray-300 text-[#0c3082] shadow-sm"
                       onchange="document.getElementById('password').type = this.checked ? 'text' : 'password';
                                 document.getElementById('password_confirmation').type = this.checked ? 'text' : 'password';">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Mostrar contraseñas</span>
            </label>
        </div>

        <button type="submit"
                class="mt-6 w-full rounded-md bg-[#0c3082] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0a2668] focus:outline-none focus:ring-2 focus:ring-[#0c3082] focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            Cambiar contraseña
        </button>
    </form>
</x-guest-layout>
