<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-lg border border-rose-700 bg-rose-700 px-4 py-2 leading-6 font-semibold text-white hover:border-rose-600 hover:bg-rose-600 hover:text-white focus:outline-none focus:ring-3 focus:ring-rose-400/50 active:border-rose-700 active:bg-rose-700']) }}>
    {{ $slot }}
</button>
