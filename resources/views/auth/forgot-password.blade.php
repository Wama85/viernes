<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Recuperar contraseña</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Escribe tu correo registrado y te enviaremos un código de 6 dígitos.
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <button type="submit"
                class="mt-6 w-full rounded-md bg-[#0c3082] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0a2668] focus:outline-none focus:ring-2 focus:ring-[#0c3082] focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            Enviar código
        </button>
    </form>

    <p class="mt-4 text-center">
        <a href="{{ route('login') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Volver a iniciar sesión</a>
    </p>
</x-guest-layout>
