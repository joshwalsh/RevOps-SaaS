@props(['value'])

<label {{ $attributes->merge(['class' => 'inline-block font-medium text-gray-700']) }}>
    {{ $value ?? $slot }}
</label>
