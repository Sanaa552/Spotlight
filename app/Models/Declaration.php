<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    // ----- Opérations UML: soumettre(), publier(), cloturer() -----

    public function soumettre(): static
    {
        $this->update(['statut' => 'en_attente']);

        return $this;
    }

    public function publier(): static
    {
        $this->update(['statut' => 'validee']);

        return $this;
    }

    public function cloturer(): static
    {
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