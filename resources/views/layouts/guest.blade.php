<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="mx-auto flex min-h-dvh w-full min-w-80 flex-col bg-gray-100">
            <main class="flex max-w-full flex-auto flex-col">
                <div class="relative mx-auto flex min-h-dvh w-full max-w-xl flex-col justify-center overflow-hidden p-4 lg:p-8">
                    <div class="flex flex-col overflow-hidden rounded-xl border border-gray-300/75 bg-white ring-4 ring-gray-200/50">
                        <div class="grow px-6 py-10 md:px-12 md:py-14">
                            <header class="mb-8 text-center">
                                <a href="/" wire:navigate class="mb-8 inline-flex items-center justify-center gap-2 text-lg">
                                    <x-application-logo />
                                </a>
                            </header>

                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
