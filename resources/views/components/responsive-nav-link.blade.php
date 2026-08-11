@props(['active'])

@php
$classes = ($active ?? false)
            ? 'group flex items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-2.5 py-2 text-sm font-medium text-gray-900'
            : 'group flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-800 active:border-blue-100';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
