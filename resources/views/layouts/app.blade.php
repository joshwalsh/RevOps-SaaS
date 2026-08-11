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
        <div
            x-data="{ mobileSidebarOpen: false, desktopSidebarOpen: true }"
            x-bind:class="{ 'lg:pl-64': desktopSidebarOpen }"
            class="mx-auto flex min-h-dvh w-full min-w-80 flex-col bg-gray-100"
        >
            <!-- Sidebar -->
            <nav
                x-bind:class="{
                    '-translate-x-full': !mobileSidebarOpen,
                    'translate-x-0': mobileSidebarOpen,
                    'lg:-translate-x-full': !desktopSidebarOpen,
                    'lg:translate-x-0': desktopSidebarOpen,
                }"
                class="fixed top-0 bottom-0 left-0 z-50 flex h-full w-full -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-500 ease-out lg:w-64 lg:translate-x-0"
                aria-label="Main Sidebar Navigation"
            >
                <div class="flex h-16 w-full flex-none items-center justify-between px-4 lg:justify-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="text-lg" />
                    </a>

                    <div class="lg:hidden">
                        <button
                            x-on:click="mobileSidebarOpen = false"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm leading-5 font-semibold text-gray-800 hover:border-gray-300 hover:text-gray-900 hover:shadow-xs focus:ring-3 focus:ring-gray-300/25 active:border-gray-200 active:shadow-none"
                        >
                            <svg class="inline-block size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="overflow-y-auto">
                    <div class="w-full p-4">
                        <nav class="space-y-1">
                            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                                <span class="flex flex-none items-center text-blue-500">
                                    <svg class="inline-block size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                    </svg>
                                </span>
                                <span class="grow">{{ __('Dashboard') }}</span>
                            </x-nav-link>

                            <x-nav-link :href="route('profile')" :active="request()->routeIs('profile')" wire:navigate>
                                <span class="flex flex-none items-center text-gray-400">
                                    <svg class="inline-block size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </span>
                                <span class="grow">{{ __('Profile') }}</span>
                            </x-nav-link>
                        </nav>
                    </div>
                </div>
            </nav>

            <!-- Header -->
            <header
                x-bind:class="{ 'lg:pl-64': desktopSidebarOpen }"
                class="fixed top-0 right-0 left-0 z-30 flex h-16 flex-none items-center bg-white shadow-xs"
            >
                <div class="mx-auto flex w-full max-w-10xl justify-between px-4 lg:px-8">
                    <div class="flex items-center gap-2">
                        <div class="hidden lg:block">
                            <button
                                x-on:click="desktopSidebarOpen = !desktopSidebarOpen"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm leading-5 font-semibold text-gray-800 hover:border-gray-300 hover:text-gray-900 hover:shadow-xs focus:ring-3 focus:ring-gray-300/25 active:border-gray-200 active:shadow-none"
                            >
                                <svg class="inline-block size-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        <div class="lg:hidden">
                            <button
                                x-on:click="mobileSidebarOpen = !mobileSidebarOpen"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm leading-5 font-semibold text-gray-800 hover:border-gray-300 hover:text-gray-900 hover:shadow-xs focus:ring-3 focus:ring-gray-300/25 active:border-gray-200 active:shadow-none"
                            >
                                <svg class="inline-block size-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <livewire:layout.navigation />
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex max-w-full flex-auto flex-col pt-16">
                <div class="mx-auto w-full max-w-10xl p-4 lg:p-8">
                    @if (isset($header))
                        <div class="mb-6 rounded-lg border border-gray-200 bg-white px-4 py-4 shadow-xs sm:px-6">
                            {{ $header }}
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
