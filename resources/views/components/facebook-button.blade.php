@props(['label'])

<a {{ $attributes->merge([
    'class' => 'mx-auto flex w-fit max-w-full items-center justify-center gap-2 rounded-md bg-[#1877F2] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#166FE5] focus:outline-none focus:ring-2 focus:ring-[#1877F2] focus:ring-offset-2',
]) }}>
    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-white text-[#1877F2]">
        <svg viewBox="0 0 24 24" aria-hidden="true" class="h-3 w-3 fill-current">
            <path d="M14.2 8.4V6.9c0-.7.5-.9.9-.9h2.2V2.3L14.2 2c-3.4 0-4.8 2.1-4.8 4.6v1.8H6.2v4.1h3.2V22h4.1v-9.5h3.2l.5-4.1h-3z" />
        </svg>
    </span>
    <span>{{ $label }}</span>
</a>
