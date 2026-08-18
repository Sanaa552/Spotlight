<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Localisation extends Model
{
    protected $fillable = [
        'declaration_id',
        'adresse',
        'latitude',
        'longitude',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(Declaration::class);
    }

    // Opération UML: fournir() -> fournit les coordonnées, ex. pour affichage carte
    public function fournir(): array
    {
        return [
            'adresse' => $this->adresse,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}