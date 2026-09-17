<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceJointe extends Model
{
    protected $table = 'pieces_jointes';

    protected $fillable = [
        'declaration_id',
        'type_document',
        'disque',
        'chemin',
        'nom_original',
        'type_mime',
        'taille',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(Declaration::class);
    }

    /** Toute pièce jointe passe par une route autorisée. */
    public function url(): string
    {
        return route('pieces-jointes.telecharger', $this);
    }

    public function estImage(): bool
    {
        return str_starts_with($this->type_mime ?? '', 'image/');
    }

    public function estDeclarationPerte(): bool
    {
        return $this->type_document === 'declaration_perte';
    }
}
