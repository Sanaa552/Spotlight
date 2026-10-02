@props(['value' => null, 'required' => false])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700']) }}>
    {{ $value ?? $slot }}
    @if ($required)
        <span class="text-alerte" aria-hidden="true">*</span><span class="sr-only"> (obligatoire)</span>
    @endif
</label>
