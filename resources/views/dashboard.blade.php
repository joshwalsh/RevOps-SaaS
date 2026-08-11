<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="rounded-lg border border-gray-200 bg-white shadow-xs">
        <div class="p-6 text-gray-900">
            {{ __("You're logged in!") }}
        </div>
    </div>
</x-app-layout>
