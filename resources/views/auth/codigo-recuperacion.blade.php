<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Ingresa el código</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Si <strong>{{ $correoOculto }}</strong> está registrado, enviamos un código de 6 dígitos. Vale 10 minutos.
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.codigo.verificar') }}">
        @csrf

        <div>
            <x-input-label for="codigo" value="Código de verificación" />
            <x-text-input id="codigo" class="mt-1 block w-full" type="text" name="codigo"
                          inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code"
                          required autofocus
                          style="letter-spacing:0.5em; text-align:center; font-size:22px;"
                          oninput="this.value = this.value.replace(/\D/g, '')" />
            <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
        </div>

        <button type="submit"
                class="mt-6 w-full rounded-md bg-[#0c3082] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0a2668] focus:outline-none focus:ring-2 focus:ring-[#0c3082] focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            Verificar código
        </button>
    </form>

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('password.codigo.reenviar') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Reenviar código</button>
        </form>
        <a href="{{ route('password.request') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cambiar correo</a>
    </div>
</x-guest-layout>
