Bonjour {{ $notifiable->name }},

Votre déclaration #{{ $declarationId }} est confirmée sur Facebook, Instagram et Spotlight.

Votre dossier : {{ $declarationUrl }}
@if ($facebookUrl)
Facebook : {{ $facebookUrl }}
@endif
@if ($instagramUrl)
Instagram : {{ $instagramUrl }}
@endif

Les justificatifs privés ne figurent pas dans les publications publiques.

Spotlight
