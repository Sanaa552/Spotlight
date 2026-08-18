@props(['disabled' => false])

<input @disabled($disabled) type="checkbox" {{ $attributes->merge(['class' => 'rounded border-gray-300 text-alerte shadow-sm focus:ring-alerte']) }}>