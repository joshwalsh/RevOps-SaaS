<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 leading-6 font-semibold text-gray-800 hover:border-gray-300 hover:text-gray-900 hover:shadow-xs focus:outline-none focus:ring-3 focus:ring-gray-300/25 active:border-gray-200 active:shadow-none disabled:opacity-25']) }}>
    {{ $slot }}
</button>
