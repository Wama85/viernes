<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SISWAMA') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-2">

            {{-- Panel de marca (solo pantallas grandes) --}}
            <div class="hidden flex-col items-center justify-center bg-[#0c3082] px-12 text-center text-white lg:flex">
                <span class="flex h-44 w-44 items-center justify-center rounded-full bg-white p-2 shadow-2xl">
                    <x-application-logo class="h-40 w-40 object-contain" />
                </span>
                <h1 class="mt-8 text-4xl font-bold tracking-wide">{{ config('app.name') }}</h1>
                <p class="mt-3 max-w-sm text-blue-100/80">
                    Sistema de cotizaciones, recibos y facturación.
                </p>
            </div>

            {{-- Formulario --}}
            <div class="flex min-h-screen items-center justify-center bg-gray-100 px-6 py-10 dark:bg-gray-900">
                <div class="w-full max-w-md">

                    {{-- Marca (solo celular) --}}
                    <div class="mb-8 flex flex-col items-center lg:hidden">
                        <span class="flex h-24 w-24 items-center justify-center rounded-full bg-white p-1 shadow-lg">
                            <x-application-logo class="h-20 w-20 object-contain" />
                        </span>
                        <h1 class="mt-4 text-2xl font-bold tracking-wide text-[#0c3082] dark:text-blue-200">{{ config('app.name') }}</h1>
                    </div>

                    <div class="rounded-xl bg-white p-8 shadow-lg dark:bg-gray-800">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
