<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceJointe extends Model
{
    protected $table = 'pieces_jointes';

    protected $fillable = [
        'declaration_id',
        'chemin',
        'nom_original',
        'type_mime',
        'taille',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(Declaration::class);
    }

    /** URL publique du fichier (stocké sur le disque "public") */
    public function url(): string
    {
        return asset('storage/' . $this->chemin);
    }

    public function estImage(): bool
    {
        return str_starts_with($this->type_mime ?? '', 'image/');
    }
}