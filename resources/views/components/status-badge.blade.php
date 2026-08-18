@props(['statut'])

@php
    $styles = match ($statut) {
        'en_attente' => 'bg-yellow-100 text-yellow-800',
        'validee' => 'bg-green-100 text-green-800',
        'rejetee' => 'bg-red-100 text-red-800',
        'cloturee' => 'bg-gray-200 text-gray-700',
        default => 'bg-gray-100 text-gray-600',
    };

    $labels = [
        'en_attente' => 'En attente',
        'validee' => 'Validée',
        'rejetee' => 'Rejetée',
        'cloturee' => 'Clôturée',
    ];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold $styles"]) }}>
    {{ $labels[$statut] ?? $statut }}
</span>