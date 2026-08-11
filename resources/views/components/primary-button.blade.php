<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-lg border border-blue-700 bg-blue-700 px-4 py-2 leading-6 font-semibold text-white hover:border-blue-600 hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-3 focus:ring-blue-400/50 active:border-blue-700 active:bg-blue-700']) }}>
    {{ $slot }}
</button>
