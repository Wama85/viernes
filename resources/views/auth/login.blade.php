<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Iniciar sesión</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ingresa con tu cuenta para continuar.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" data-validar novalidate>
        @csrf

        <div data-resumen class="val-resumen" hidden></div>

        <!-- Email Address -->
        <x-campo-validado name="email" type="email" label="Correo electrónico"
                          :obligatorio="true" :maxlength="100"
                          reglas="requerido|correo|max:100"
                          placeholder="nombre@dominio.com" inputmode="email"
                          autocomplete="username" autofocus />

        <!-- Password -->
        <x-campo-clave name="password" label="Contraseña"
                       :obligatorio="true"
                       reglas="requerido"
                       autocomplete="current-password" />

        <!-- Remember Me + Olvidé mi contraseña -->
        <div class="mt-4 flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-[#0c3082] shadow-sm focus:ring-[#0c3082] dark:border-gray-700 dark:bg-gray-900" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Mantener la sesión iniciada</span>
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit"
                class="mt-6 w-full rounded-md bg-[#0c3082] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0a2668] focus:outline-none focus:ring-2 focus:ring-[#0c3082] focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            Ingresar
        </button>
    </form>

    <x-validacion />

    {{-- Separador --}}
    <div style="display:flex; align-items:center; gap:12px; margin:22px 0 16px 0;">
        <div style="flex:1; height:1px; background:#d1d5db;"></div>
        <span style="font-size:12px; color:#6b7280;">o</span>
        <div style="flex:1; height:1px; background:#d1d5db;"></div>
    </div>

    {{-- Inicio de sesión con Google --}}
    <a href="{{ route('google.redirect') }}"
       style="display:flex; align-items:center; justify-content:center; gap:10px; width:100%; box-sizing:border-box;
              padding:10px 16px; border:1px solid #d1d5db; border-radius:6px; background:#ffffff;
              color:#374151; font-size:14px; font-weight:600; text-decoration:none;">
        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Continuar con Google
    </a>

    <p style="margin-top:10px; text-align:center; font-size:11px; color:#6b7280;">
        Solo pueden entrar los usuarios registrados con su correo de Gmail.
    </p>
</x-guest-layout>
