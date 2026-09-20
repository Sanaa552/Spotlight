<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rapprochement extends Model
{
    protected $fillable = [
        'perte_id', 'decouverte_id', 'moderateur_id', 'statut', 'verifie_at',
        'proprietaire_confirme_at', 'decouvreur_confirme_at', 'restitue_at',
    ];

    protected function casts(): array
    {
        return [
            'verifie_at' => 'datetime',
            'proprietaire_confirme_at' => 'datetime',
            'decouvreur_confirme_at' => 'datetime',
            'restitue_at' => 'datetime',
        ];
    }

    public function perte(): BelongsTo
    {
        return $this->belongsTo(Declaration::class, 'perte_id');
    }

    public function decouverte(): BelongsTo
    {
        return $this->belongsTo(Declaration::class, 'decouverte_id');
    }

    public function moderateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderateur_id');
    }
}
