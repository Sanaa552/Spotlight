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
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }
    
        public function commentaires(): HasMany
    {
        return $this->hasMany(Commentaire::class)->latest();
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
