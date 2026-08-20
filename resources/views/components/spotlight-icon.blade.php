@props(['class' => 'h-10 w-10'])

<span class="{{ $class }} inline-block animate-spotlight-float">
    <svg viewBox="0 0 190 190" class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <clipPath id="spotlight-radar-clip">
                <circle cx="95" cy="95" r="60" />
            </clipPath>
        </defs>

        <!-- Manche de la loupe -->
        <line x1="140" y1="140" x2="171" y2="171" stroke="#D8DCE3" stroke-width="14" stroke-linecap="round" />

        <!-- Fond du radar -->
        <circle cx="95" cy="95" r="62" fill="#02040C" />

        <!-- Grille radar (statique) -->
        <circle cx="95" cy="95" r="30" fill="none" stroke="#12579B" stroke-width="1" opacity="0.35" />
        <circle cx="95" cy="95" r="46" fill="none" stroke="#12579B" stroke-width="1" opacity="0.25" />

        <!-- Balayage radar rouge (tourne en continu) -->
        <g clip-path="url(#spotlight-radar-clip)">
            <path d="M95,95 L151.4,74.5 A60,60 0 0,1 147,125 Z"
                  fill="#E31E24" opacity="0.55" class="animate-spotlight-radar-spin" />
        </g>

        <!-- Ampoule (statique) -->
        <circle cx="95" cy="112" r="13" fill="none" stroke="#D8DCE3" stroke-width="2.5" />
        <path d="M87,123 L103,123 L100,132 L90,132 Z" fill="none" stroke="#D8DCE3" stroke-width="2" />
        <circle cx="95" cy="112" r="5" fill="#FDC105" />

        <!-- Pin de localisation (monte-descend) -->
        <g class="animate-spotlight-pin-bounce">
            <path d="M95,64 C101,64 106,69 106,75 C106,82 95,92 95,92 C95,92 84,82 84,75 C84,69 89,64 95,64 Z"
                  fill="#E31E24" />
        </g>

        <!-- Cadre de la loupe -->
        <circle cx="95" cy="95" r="64" fill="none" stroke="#D8DCE3" stroke-width="8" />
    </svg>
</span>