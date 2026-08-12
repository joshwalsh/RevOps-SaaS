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
        @php
            $isPlatformAdmin = auth()->user()->currentOrganization?->is_super_admin;
        @endphp

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
                class="fixed top-0 bottom-0 left-0 z-50 flex h-full w-full -translate-x-full flex-col border-r transition-transform duration-500 ease-out lg:w-64 lg:translate-x-0 {{ $isPlatformAdmin ? 'border-gray-800 bg-gray-900' : 'border-gray-200 bg-white' }}"
                aria-label="Main Sidebar Navigation"
            >
                <div class="flex h-16 w-full flex-none items-center gap-2 px-4 lg:justify-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="{{ $isPlatformAdmin ? 'rounded-lg bg-white px-2 py-1' : '' }}">
                        <x-application-logo class="text-lg" />
                    </a>

                    @if ($isPlatformAdmin)
                        <span class="rounded-full bg-red-500 px-2 py-0.5 text-xs font-medium text-white">
                            {{ __('Platform Admin') }}
                        </span>
                    @endif

                    <div class="ms-auto lg:hidden">
                        <button
                            x-on:click="mobileSidebarOpen = false"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm leading-5 font-semibold focus:ring-3 active:shadow-none {{ $isPlatformAdmin ? 'border-gray-700 bg-gray-800 text-gray-100 hover:border-gray-600 hover:text-white focus:ring-gray-500/25 active:border-gray-700' : 'border-gray-200 bg-white text-gray-800 hover:border-gray-300 hover:text-gray-900 hover:shadow-xs focus:ring-gray-300/25 active:border-gray-200' }}"
                        >
                            <svg class="inline-block size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="overflow-y-auto">
                    <div class="w-full p-4">
                        <nav class="space-y-1 {{ $isPlatformAdmin ? 'rounded-lg bg-white p-2 shadow-xs' : '' }}">
                            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                                <span class="flex flex-none items-center text-blue-500">
                                    <svg class="inline-block size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                    </svg>
                                </span>
                                <span class="grow">{{ __('Dashboard') }}</span>
                            </x-nav-link>

                            @if ($isPlatformAdmin)
                                <x-nav-link :href="route('admin.organizations')" :active="request()->routeIs('admin.organizations')" wire:navigate>
                                    <span class="flex flex-none items-center text-gray-400">
                                        <svg class="inline-block size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
                                        </svg>
                                    </span>
                                    <span class="grow">{{ __('Organizations') }}</span>
                                </x-nav-link>

                                <x-nav-link :href="route('admin.members')" :active="request()->routeIs('admin.members')" wire:navigate>
                                    <span class="flex flex-none items-center text-gray-400">
                                        <svg class="inline-block size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                        </svg>
                                    </span>
                                    <span class="grow">{{ __('Members') }}</span>
                                </x-nav-link>
                            @elseif (auth()->user()->currentOrganization)
                                <x-nav-link :href="route('organizations.members', auth()->user()->currentOrganization)" :active="request()->routeIs('organizations.members')" wire:navigate>
                                    <span class="flex flex-none items-center text-gray-400">
                                        <svg class="inline-block size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                        </svg>
                                    </span>
                                    <span class="grow">{{ __('Members') }}</span>
                                </x-nav-link>
                            @endif

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
                        <livewire:layout.organization-switcher />

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
