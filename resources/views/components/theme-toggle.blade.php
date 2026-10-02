<button type="button" data-theme-toggle title="Passer au thème clair" aria-label="Passer au thème clair"
        {{ $attributes->merge(['class' => 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-argent/70 transition hover:bg-white/5 hover:text-argent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-alerte']) }}>
    <x-icon name="sun" class="theme-icon-sun h-5 w-5" />
    <x-icon name="moon" class="theme-icon-moon h-5 w-5" />
</button>
