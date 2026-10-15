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

                <style>
            [x-cloak] { display: none !important; }

            /* Barra de desplazamiento del menú lateral: delgada y translúcida */
            .menu-scroll { scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, .25) transparent; }
            .menu-scroll::-webkit-scrollbar { width: 6px; }
            .menu-scroll::-webkit-scrollbar-track { background: transparent; }
            .menu-scroll::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, .22); border-radius: 9999px; }
            .menu-scroll::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, .4); }
        </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ menu: false }" class="min-h-screen bg-gray-100 dark:bg-gray-900">

            {{-- Menú lateral --}}
            @include('layouts.navigation')

            <div class="lg:pl-64">

                {{-- Barra superior (solo celular) --}}
                <div class="sticky top-0 z-20 flex h-14 items-center gap-3 bg-[#0c3082] px-4 text-white lg:hidden">
                    <button @click="menu = true" class="rounded-md p-1.5 hover:bg-white/10 focus:outline-none" aria-label="Abrir menú">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="font-bold tracking-wide">{{ config('app.name') }}</span>
                </div>

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow dark:bg-gray-800">
                        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
