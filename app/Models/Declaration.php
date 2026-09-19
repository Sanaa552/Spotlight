<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use LogicException;

class Declaration extends Model
{
    protected $fillable = [
        'user_id',
        'moderateur_id',
        'type',
        'categorie',
        'description',
        'lieu',
        'statut',
        'type_perte',
        'type_decouverte',
        'photo_path',
        'facebook_post_id',
        'instagram_post_id',
        'facebook_post_url',
        'instagram_post_url',
        'publication_status',
        'publication_error',
        'motif_rejet',
        'cloturee_at',
    ];

    protected function casts(): array
    {
        return [
            'cloturee_at' => 'datetime',
        ];
    }

    // ----- Relations -----

    public function citoyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function moderateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderateur_id');
    }

    public function localisation(): HasOne
    {
        return $this->hasOne(Localisation::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function publicationReminders(): HasMany
    {
        return $this->hasMany(PublicationReminder::class)->latest();
    }

    public function rapprochementDecouverte(): HasOne
    {
        return $this->hasOne(Rapprochement::class, 'decouverte_id');
    }

    public function rapprochementsPerte(): HasMany
    {
        return $this->hasMany(Rapprochement::class, 'perte_id');
    }

    public function piecesJointes(): HasMany
    {
        return $this->hasMany(PieceJointe::class);
    }

    public function declarationPerte(): HasOne
    {
        return $this->hasOne(PieceJointe::class)->where('type_document', 'declaration_perte');
    }

    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return $this->photoEnAttente()
            ? route('declarations.photo-privee', $this)
            : Storage::disk('public')->url($this->photo_path);
    }

    public function photoEnAttente(): bool
    {
        return str_starts_with($this->photo_path ?? '', 'photos-en-attente/');
    }

    public function publicationMessage(): string
    {
        $precision = $this->type === 'perte' ? $this->type_perte : $this->type_decouverte;

        return implode("\n\n", array_filter([
            "SPOTLIGHT - Declaration #{$this->id}",
            ucfirst($this->type).' : '.$this->categorie,
            $precision,
            $this->description,
            'Secteur : '.($this->lieu ?: 'Non précisé'),
        ]));
    }
    
        public function commentaires(): HasMany
    {
        return $this->hasMany(Commentaire::class)->with('parent.auteur')->latest();
    }
    // ----- Opérations UML: soumettre(), publier(), cloturer() -----

    public function soumettre(): static
    {
        $this->update(['statut' => 'en_attente']);

        return $this;
    }

    public function publier(): static
    {
        if (! $this->facebook_post_id || ! $this->instagram_post_id) {
            throw new LogicException('La publication Facebook et Instagram doit être confirmée avant validation.');
        }

        $this->update(['statut' => 'validee']);

        return $this;
    }

    public function confirmerSignalement(): static
    {
        if ($this->type !== 'decouverte' || $this->categorie !== 'personne') {
            throw new LogicException('Seule une découverte de personne peut être confirmée sans publication.');
        }

        $this->update(['statut' => 'validee']);

        return $this;
    }

    public function scopePublique($query)
    {
        return $query->whereNotNull('facebook_post_id')
            ->whereNotNull('instagram_post_id')
            ->whereNotNull('photo_path')
            ->where('photo_path', 'like', 'photos-publiques/%')
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('type', 'perte')
                        ->whereHas('piecesJointes', fn ($pieces) => $pieces->where('type_document', 'declaration_perte'));
                })->orWhere(function ($query) {
                    $query->where('type', 'decouverte')
                        ->where('categorie', 'objet')
                        ->whereHas('piecesJointes', fn ($pieces) => $pieces->where('type_document', 'preuve_decouverte'))
                        ->whereHas('piecesJointes', fn ($pieces) => $pieces->where('type_document', 'preuve_signalement'));
                });
            });
    }

    public static function perteObjetPublique(int $id): ?self
    {
        return self::publique()->whereKey($id)->where('type', 'perte')
            ->where('categorie', 'objet')->where('statut', 'validee')->first();
    }

    public function cloturer(): static
    {
        if ($this->statut !== 'validee') {
            throw new LogicException('Seule une déclaration validée peut être clôturée.');
        }

        $this->update([
            'statut' => 'cloturee',
            'cloturee_at' => now(),
        ]);

        return $this;
    }

    // ----- Scopes pratiques -----

    public function scopePertes($query)
    {
        return $query->where('type', 'perte');
    }

    public function scopeDecouvertes($query)
    {
        return $query->where('type', 'decouverte');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }
}
