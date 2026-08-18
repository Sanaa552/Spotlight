@props(['type'])

@php
    $styles = match ($type) {
        'perte' => 'bg-orange-100 text-orange-800',
        'decouverte' => 'bg-teal-100 text-teal-800',
        default => 'bg-gray-100 text-gray-600',
    };

    $labels = [
        'perte' => '🔍 Perte',
        'decouverte' => '📢 Découverte',
    ];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold $styles"]) }}>
    {{ $labels[$type] ?? $type }}
</span>