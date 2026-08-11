@props(['align' => 'right', 'width' => '48', 'contentClasses' => ''])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div class="relative inline-block" x-data="{ open: false }" x-on:click.outside="open = false" x-on:close.stop="open = false">
    <div x-on:click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-cloak
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-90"
            x-on:keydown.escape.window="open = false"
            class="absolute z-50 mt-2 {{ $width }} {{ $alignmentClasses }} rounded-lg shadow-xl"
            x-on:click="open = false">
        <div class="divide-y divide-gray-100 rounded-lg bg-white ring-1 ring-black/5 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
