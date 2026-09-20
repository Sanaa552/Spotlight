<x-app-layout>
    @php
        $backRoute = auth()->user()->isCitoyen()
            ? route('declarations.index')
            : route('moderation.index');
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-argent leading-tight">
                {{ __('Déclaration') }} #{{ $declaration->id }}
            </h2>
            <a href="{{ $backRoute }}" class="text-sm text-alerte hover:underline">
                Retour
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-xl">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="bg-laiton/10 border border-laiton/30 text-laiton px-4 py-3 rounded-lg text-sm">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($declaration->type === 'decouverte' && $declaration->categorie === 'objet')
                @php $match = $declaration->rapprochementDecouverte; @endphp
                <section class="bg-white border border-gray-100 rounded-md p-5">
                    <h3 class="text-base font-semibold text-gray-900">Correspondance avec une perte</h3>
                    @if ($match && $match->statut !== 'rejete')
                        <p class="mt-2 text-sm text-gray-700">Perte #{{ $match->perte_id }} :
                            <a href="{{ auth()->user()->isCitoyen() ? route('public.declarations.show', $match->perte) : route('moderation.declarations.show', $match->perte) }}" class="font-medium text-azur underline">{{ $match->perte->type_perte ?: 'Objet perdu' }}</a>
                        </p>
                        <p class="mt-1 text-sm text-gray-600">{{ $match->statut === 'propose' ? 'Proposition en attente de vérification.' : ($match->statut === 'verifie' ? 'Correspondance vérifiée ; la remise reste à confirmer.' : 'Restitution confirmée.') }}</p>
                        @if ($match->statut === 'verifie' && auth()->user()->isCitoyen() && $declaration->user_id === auth()->id())
                            @if ($match->decouvreur_confirme_at)
                                <p class="mt-3 text-sm text-sonar-dark">Vous avez confirmé la remise. La modération attend les autres vérifications.</p>
                            @else
                                <form method="POST" action="{{ route('rapprochements.confirmer', $match) }}" class="mt-3" x-data="{ confirm: false }">
                                    @csrf
                                    <button type="button" x-on:click="confirm = true" class="text-sm font-semibold text-sonar-dark underline">Confirmer que l’objet a été remis</button>
                                    <div x-show="confirm" x-cloak class="mt-2 border-l-2 border-sonar pl-3 text-sm text-gray-700">
                                        Confirmez uniquement après la remise réelle.
                                        <button type="submit" class="ml-2 font-semibold text-sonar-dark underline">Oui, confirmer</button>
                                        <button type="button" x-on:click="confirm = false" class="ml-2 text-gray-500 underline">Annuler</button>
                                    </div>
                                </form>
                            @endif
                        @endif
                        @if (!auth()->user()->isCitoyen() && $match->statut === 'propose')
                            <div class="mt-4 flex flex-wrap gap-3" x-data="{ action: '' }">
                                <button type="button" x-on:click="action = 'verifier'" class="text-sm font-semibold text-sonar-dark underline">Vérifier la correspondance</button>
                                <button type="button" x-on:click="action = 'rejeter'" class="text-sm font-semibold text-alerte underline">Refuser</button>
                                <form x-show="action === 'verifier'" x-cloak method="POST" action="{{ route('moderation.rapprochements.verifier', $match) }}" class="w-full text-sm text-gray-700">
                                    @csrf
                                    Vérifiez les preuves des deux dossiers avant de confirmer.
                                    <button type="submit" class="ml-2 font-semibold text-sonar-dark underline">Confirmer</button>
                                </form>
                                <form x-show="action === 'rejeter'" x-cloak method="POST" action="{{ route('moderation.rapprochements.rejeter', $match) }}" class="w-full text-sm text-gray-700">
                                    @csrf
                                    La découverte restera indépendante.
                                    <button type="submit" class="ml-2 font-semibold text-alerte underline">Confirmer le refus</button>
                                </form>
                            </div>
                        @endif
                        @if (!auth()->user()->isCitoyen() && $match->statut === 'verifie')
                            <p class="mt-3 text-xs text-gray-600">Déclarant de perte : {{ $match->perte->citoyen->name }} ({{ $match->perte->citoyen->email }}). Déclarant de découverte : {{ $declaration->citoyen->name }} ({{ $declaration->citoyen->email }}). Coordonnez la remise avec les autorités.</p>
                            <p class="mt-2 text-xs text-gray-600">Propriétaire : {{ $match->proprietaire_confirme_at ? 'remise confirmée' : 'confirmation attendue' }}. Découvreur : {{ $match->decouvreur_confirme_at ? 'remise confirmée' : 'confirmation attendue' }}.</p>
                            @if ($match->proprietaire_confirme_at && $match->decouvreur_confirme_at)
                                <form method="POST" action="{{ route('moderation.rapprochements.finaliser', $match) }}" class="mt-3" x-data="{ confirm: false }">
                                    @csrf
                                    <button type="button" x-on:click="confirm = true" class="text-sm font-semibold text-sonar-dark underline">Clôturer les deux dossiers</button>
                                    <div x-show="confirm" x-cloak class="mt-2 text-sm text-gray-700">La restitution a-t-elle été contrôlée ?
                                        <button type="submit" class="ml-2 font-semibold text-sonar-dark underline">Oui, clôturer</button>
                                    </div>
                                </form>
                            @endif
                        @endif
                    @else
                        <p class="mt-2 text-sm text-gray-600">{{ $match ? 'La correspondance proposée a été refusée.' : 'Aucune perte liée pour le moment.' }} Une découverte peut rester indépendante.</p>
                        @if (auth()->user()->isCitoyen() && $declaration->user_id === auth()->id() && in_array($declaration->statut, ['en_attente', 'validee'], true))
                            <a href="{{ route('rapprochements.pertes', ['decouverte' => $declaration->id]) }}" class="mt-3 inline-block text-sm font-semibold text-azur underline">Chercher une perte d’objet correspondante</a>
                        @endif
                    @endif
                </section>
            @endif

            @if ($declaration->type === 'perte' && $declaration->categorie === 'objet')
                @foreach ($declaration->rapprochementsPerte->whereIn('statut', ['verifie', 'restitue']) as $match)
                    <section class="bg-white border border-gray-100 rounded-md p-5">
                        <h3 class="text-base font-semibold text-gray-900">Découverte rapprochée</h3>
                        <p class="mt-2 text-sm text-gray-700">Une découverte #{{ $match->decouverte_id }} a été vérifiée par la modération. Organisez la remise avec les autorités ; vos coordonnées ne sont pas communiquées automatiquement.</p>
                        @if ($match->statut === 'verifie' && auth()->user()->isCitoyen() && $declaration->user_id === auth()->id())
                            @if ($match->proprietaire_confirme_at)
                                <p class="mt-3 text-sm text-sonar-dark">Vous avez confirmé la réception de l’objet.</p>
                            @else
                                <form method="POST" action="{{ route('rapprochements.confirmer', $match) }}" class="mt-3" x-data="{ confirm: false }">
                                    @csrf
                                    <button type="button" x-on:click="confirm = true" class="text-sm font-semibold text-sonar-dark underline">Confirmer que j’ai récupéré mon objet</button>
                                    <div x-show="confirm" x-cloak class="mt-2 text-sm text-gray-700">Confirmez uniquement après la remise réelle.
                                        <button type="submit" class="ml-2 font-semibold text-sonar-dark underline">Oui, confirmer</button>
                                    </div>
                                </form>
                            @endif
                        @endif
                    </section>
                @endforeach
            @endif

            @if (auth()->user()->isCitoyen() && $declaration->type === 'decouverte' && $declaration->categorie === 'objet')
                <div class="bg-azur/10 border border-azur/30 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-sm text-azur">
                        Consultez les postes référencés dans votre ville pour remettre l’objet ou retrouver celui où vous l’avez signalé. La carte ne confirme pas le dépôt : conservez le justificatif remis par les autorités.
                    </p>
                    <a href="{{ route('declarations.commissariats', $declaration) }}"
                       class="shrink-0 inline-flex items-center gap-2 justify-center px-5 py-2 bg-azur text-white text-xs font-semibold uppercase tracking-widest rounded-lg hover:bg-azur-dark transition">
                        <x-icon name="car" class="w-4 h-4" />
                        Accéder à la carte
                    </a>
                </div>
            @endif

            {{-- En-tête statut --}}
            <div x-data="publicationTracker('{{ route('declarations.publication-status', $declaration) }}', '{{ $declaration->publication_status }}')"
                 class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6 border-l-4 {{ $declaration->type === 'perte' ? 'border-l-alerte' : 'border-l-sonar' }}">
                <div class="flex items-center gap-2 mb-4">
                    <x-type-badge :type="$declaration->type" />
                    <x-status-badge :statut="$declaration->statut" />
                    <span class="text-xs text-gray-400">
                        Créée {{ $declaration->created_at->diffForHumans() }}
                    </span>
                </div>

                <p x-show="status === 'queued'" style="display:none" class="mb-3 text-sm text-azur" role="status">Votre déclaration attend sa publication. Elle n'est pas encore visible publiquement.</p>
                <p x-show="status === 'processing'" style="display:none" class="mb-3 flex items-center gap-2 text-sm text-azur" role="status">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-azur border-t-transparent" aria-hidden="true"></span>
                    Publication en cours. Vous serez informé du résultat.
                </p>
                <p x-show="delayed" style="display:none" class="mb-3 text-sm text-laiton" role="status">La publication prend plus de temps que prévu. Votre dossier reste suivi par la modération.</p>
                <p x-show="status === 'failed'" style="display:none" class="mb-3 text-sm text-laiton" role="status">Une étape de publication reste à confirmer. L’équipe de modération s’en occupe ; aucune action n’est nécessaire de votre part.</p>
                <p x-show="status === 'succeeded'" style="display:none" class="mb-3 text-sm text-sonar-dark" role="status">Votre déclaration est publiée sur Facebook, Instagram et Spotlight.</p>

                @if ($declaration->type === 'decouverte' && $declaration->categorie === 'personne')
                    <p class="text-sm font-medium text-azur">Signalement confidentiel : aucune photo ou localisation de cette personne n'est publiée.</p>
                @elseif ($declaration->facebook_post_id && ! $declaration->instagram_post_id)
                    <p class="text-sm font-medium text-azur">Facebook est publié. Instagram reste à confirmer ; le dossier n’apparaît pas encore dans le fil public Spotlight.</p>
                @elseif ($declaration->statut === 'en_attente')
                    <p class="text-sm text-gray-600">Ce dossier n'apparaît pas dans les listes publiques avant la vérification des preuves et la confirmation des publications.</p>
                @endif

                @if (in_array($declaration->statut, ['validee', 'cloturee'], true) && $declaration->facebook_post_id && $declaration->instagram_post_id)
                    <div class="mt-4 flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                        @if ($declaration->facebook_post_url)
                            <a href="{{ $declaration->facebook_post_url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-azur hover:underline">Voir sur Facebook</a>
                        @endif
                        @if ($declaration->instagram_post_url)
                            <a href="{{ $declaration->instagram_post_url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-azur hover:underline">Voir sur Instagram</a>
                        @endif
                    </div>
                @endif

                <h3 class="text-lg font-semibold text-gray-900">{{ $declaration->categorie }}</h3>
                @if ($declaration->type === 'perte' && $declaration->type_perte)
                    <p class="text-sm text-gray-500">{{ $declaration->type_perte }}</p>
                @elseif ($declaration->type === 'decouverte' && $declaration->type_decouverte)
                    <p class="text-sm text-gray-500">{{ $declaration->type_decouverte }}</p>
                @endif

                <p class="mt-4 text-gray-700 whitespace-pre-line">{{ $declaration->description }}</p>

                @if ($declaration->photoUrl())
                    <img src="{{ $declaration->photoUrl() }}"
                         alt="Photo publique de {{ $declaration->categorie }}"
                         class="mt-4 w-full max-h-96 object-contain bg-gray-50 rounded-md">
                @endif

                @if ($declaration->lieu)
                    <p class="mt-4 flex items-center gap-1.5 text-sm text-gray-500">
                        <x-icon name="location" class="w-4 h-4 text-gray-400" />
                        {{ $declaration->lieu }}
                    </p>
                @endif

                @if ($declaration->statut === 'rejetee' && $declaration->motif_rejet)
                    <div class="mt-4 bg-alerte/10 border border-alerte/30 text-alerte-dark px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                        <x-icon name="x-circle" class="w-4 h-4 shrink-0 mt-0.5" />
                        <span><strong>Motif du rejet :</strong> {{ $declaration->motif_rejet }}</span>
                    </div>
                @endif
            </div>

            {{-- Localisation --}}
            @if ($declaration->localisation)
                <div class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6">
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <x-icon name="location" class="w-4 h-4 text-azur" />
                        Localisation
                    </h4>
                    <p class="text-sm text-gray-700">{{ $declaration->localisation->adresse }}</p>
                    @if ($declaration->localisation->poste_prevu)
                        <p class="mt-2 text-sm text-gray-700"><span class="font-medium">Poste envisagé :</span> {{ $declaration->localisation->poste_prevu }}</p>
                        <p class="mt-1 text-xs text-gray-500">Ce choix ne confirme pas la remise de l’objet. Le justificatif des autorités reste obligatoire.</p>
                        @if ($declaration->localisation->poste_latitude !== null && $declaration->localisation->poste_longitude !== null)
                            <a href="https://www.openstreetmap.org/?mlat={{ $declaration->localisation->poste_latitude }}&mlon={{ $declaration->localisation->poste_longitude }}#map=16/{{ $declaration->localisation->poste_latitude }}/{{ $declaration->localisation->poste_longitude }}"
                               target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-sm font-medium text-azur underline">Voir l’emplacement indiqué du poste</a>
                            <p class="mt-1 text-xs text-gray-500">Coordonnées du poste indiquées par le déclarant : {{ $declaration->localisation->poste_latitude }}, {{ $declaration->localisation->poste_longitude }}. Vérifiez-les avant déplacement.</p>
                        @endif
                    @endif
                    @if ($declaration->localisation->latitude && $declaration->localisation->longitude)
                        <p class="text-xs text-gray-400 mt-1">
                            {{ $declaration->localisation->latitude }}, {{ $declaration->localisation->longitude }}
                        </p>
                    @endif

                    @if ($declaration->type === 'decouverte' && $declaration->categorie === 'objet')
                        <a href="{{ route('declarations.commissariats', $declaration) }}"
                           class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-azur text-white text-xs font-semibold uppercase tracking-widest rounded-lg hover:bg-azur-dark transition">
                            <x-icon name="car" class="w-4 h-4" />
                            Voir les commissariats proches
                        </a>
                    @endif
                </div>
            @endif

            @php
                $justificatifs = $declaration->piecesJointes->where('type_document', 'piece_jointe');
                $declarationPerte = $declaration->piecesJointes->firstWhere('type_document', 'declaration_perte');
                $preuveDecouverte = $declaration->piecesJointes->firstWhere('type_document', 'preuve_decouverte');
                $preuveSignalement = $declaration->piecesJointes->firstWhere('type_document', 'preuve_signalement');
            @endphp

            @if ($declarationPerte)
                <div class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6">
                    <h4 class="font-semibold text-gray-900 flex items-center gap-2">
                        <x-icon name="shield-check" class="w-5 h-5 text-sonar" />
                        Preuve de signalement aux autorités
                    </h4>
                    <p class="mt-1 text-xs text-gray-500">Document privé, accessible uniquement au citoyen concerné et à l’équipe de modération.</p>
                    <div class="mt-4"><x-private-attachment :piece="$declarationPerte" /></div>
                </div>
            @endif

            @if ($preuveDecouverte || $preuveSignalement)
                <div class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6">
                    <h4 class="font-semibold text-gray-900">Preuves de découverte privées</h4>
                    <p class="mt-1 text-xs text-gray-500">Accessibles uniquement au propriétaire du dossier et à la modération.</p>
                    @foreach ([$preuveDecouverte, $preuveSignalement] as $preuve)
                        @if ($preuve)
                            <div class="mt-3"><x-private-attachment :piece="$preuve" /></div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if (auth()->user()->isCitoyen() && $declaration->user_id === auth()->id() && $declaration->type === 'decouverte' && $declaration->statut === 'en_attente' && ! $preuveSignalement)
                <div class="bg-white shadow-sm border border-gray-100 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900">Ajouter la preuve de remise ou de signalement</h4>
                    <p class="mt-1 text-sm text-gray-600">Votre dossier reste en attente. Une preuve délivrée par les autorités est indispensable avant sa validation.</p>
                    <form method="POST" action="{{ route('declarations.preuve-signalement.store', $declaration) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <label for="preuve_signalement" class="block text-sm font-medium text-gray-700">Récépissé, procès-verbal ou document de signalement</label>
                        <input id="preuve_signalement" name="preuve_signalement" type="file" required accept=".jpg,.jpeg,.png,.pdf"
                               class="block w-full text-sm text-gray-600" />
                        <x-input-error :messages="$errors->get('preuve_signalement')" class="mt-2" />
                        <p class="text-xs text-gray-500">JPG, PNG ou PDF ; 10 Mo maximum. Ce document reste privé.</p>
                        <button type="submit" class="rounded-md bg-sonar px-4 py-2 text-sm font-semibold text-white hover:bg-sonar-dark">
                            Ajouter le justificatif
                        </button>
                    </form>
                </div>
            @endif

            {{-- Pièces jointes --}}
            @if ($justificatifs->isNotEmpty())
                <div class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6">
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <x-icon name="paperclip" class="w-4 h-4 text-azur" />
                        Justificatifs privés
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($justificatifs as $piece)
                            <x-private-attachment :piece="$piece" />
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Suivi / historique --}}
            <div class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6">
                <h4 class="font-semibold text-gray-900 mb-3">Suivi</h4>
                <ul class="text-sm text-gray-600 space-y-2.5">
                    <li class="flex items-center gap-2">
                        <x-icon name="clock" class="w-4 h-4 text-gray-400" />
                        Déclaration soumise le {{ $declaration->created_at->format('d/m/Y à H:i') }}
                    </li>
                    @if ($declaration->moderateur)
                        <li class="flex items-center gap-2">
                            <x-icon name="user" class="w-4 h-4 text-gray-400" />
                            Traitée par {{ $declaration->moderateur->name }}
                        </li>
                    @endif
                    @if ($declaration->statut === 'cloturee' && $declaration->cloturee_at)
                        <li class="flex items-center gap-2">
                            <x-icon name="check-circle" class="w-4 h-4 text-sonar" />
                            Clôturée le {{ $declaration->cloturee_at->format('d/m/Y à H:i') }}
                        </li>
                    @endif
                </ul>
            </div>

            {{-- Action : confirmer restitution --}}
            @if (auth()->user()->isCitoyen() && $declaration->user_id === auth()->id() && $declaration->statut === 'validee' && ! ($declaration->type === 'decouverte' && $declaration->categorie === 'personne') && ! $declaration->rapprochementDecouverte()->whereIn('statut', ['propose', 'verifie'])->exists() && ! $declaration->rapprochementsPerte()->whereIn('statut', ['propose', 'verifie'])->exists())
                <button type="button"
                        x-data=""
                        x-on:click="$dispatch('open-modal', 'confirm-restitution')"
                        class="w-full inline-flex justify-center items-center gap-2 px-4 py-3 bg-sonar border border-transparent rounded-lg font-semibold text-sm text-white uppercase tracking-widest hover:bg-sonar-dark focus:outline-none focus:ring-2 focus:ring-sonar focus:ring-offset-2 transition">
                    <x-icon name="check-circle" class="w-5 h-5" />
                    Confirmer la restitution
                </button>

                <x-modal name="confirm-restitution" maxWidth="md" focusable>
                    <form method="POST" action="{{ route('declarations.confirmer-restitution', $declaration) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-semibold text-gray-900">Confirmer la restitution ?</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Confirmez uniquement si la personne ou l’objet concerné a réellement été retrouvé et restitué. La déclaration sera alors clôturée.
                        </p>
                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                            <button type="submit" class="inline-flex items-center rounded-md bg-sonar px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-sonar-dark focus:outline-none focus:ring-2 focus:ring-sonar focus:ring-offset-2">
                                Confirmer la restitution
                            </button>
                        </div>
                    </form>
                </x-modal>
            @endif

        </div>
    </div>
</x-app-layout>
