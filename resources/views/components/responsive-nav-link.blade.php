@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-alerte text-start text-base font-medium text-argent bg-alerte/10 focus:outline-none focus:text-argent focus:bg-alerte/10 focus:border-argent transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-argent/60 hover:text-argent hover:bg-white/5 hover:border-argent/30 focus:outline-none focus:text-argent focus:bg-white/5 focus:border-argent/30 transition duration-150 ease-in-out';
@endphp

<button {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>