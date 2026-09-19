@props(['piece'])

@php
    $mime = strtolower($piece->type_mime ?? '');
    $isImage = $piece->estImage();
    $isVideo = str_starts_with($mime, 'video/') || $mime === 'application/mp4';
    $isPdf = $mime === 'application/pdf';
@endphp

<div class="min-w-0 border-t border-gray-100 pt-3">
    @if ($isImage)
        <a href="{{ $piece->previewUrl() }}" target="_blank" rel="noopener noreferrer" aria-label="Agrandir {{ $piece->nom_original }}">
            <img src="{{ $piece->previewUrl() }}" alt="Aperçu privé de {{ $piece->nom_original }}"
                 loading="lazy" class="w-full max-h-64 object-contain bg-gray-50 rounded-md">
        </a>
    @elseif ($isVideo)
        <video controls playsinline preload="metadata" class="w-full max-h-72 bg-black rounded-md">
            <source src="{{ $piece->previewUrl() }}" type="{{ $mime === 'application/mp4' ? 'video/mp4' : $mime }}">
            Votre navigateur ne peut pas lire cette vidéo. Téléchargez-la pour la consulter.
        </video>
    @elseif ($isPdf)
        <a href="{{ $piece->previewUrl() }}" target="_blank" rel="noopener noreferrer"
           class="text-sm font-medium text-azur hover:underline">Ouvrir l’aperçu PDF</a>
    @endif
    <div class="mt-2 flex items-center gap-2 min-w-0 text-sm">
        <x-icon name="paperclip" class="w-4 h-4 shrink-0 text-gray-400" />
        <span class="truncate text-gray-700" title="{{ $piece->nom_original }}">{{ $piece->nom_original }}</span>
        <a href="{{ $piece->url() }}" class="ml-auto shrink-0 text-azur hover:text-azur-dark" title="Télécharger {{ $piece->nom_original }}" aria-label="Télécharger {{ $piece->nom_original }}">
            <x-icon name="download" class="w-5 h-5" />
        </a>
    </div>
</div>
