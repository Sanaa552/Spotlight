<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Représentation UML: DeclarationPerte extends Declaration.
 * Attribut spécifique: typePerte (personne / objet).
 */
class DeclarationPerte extends Declaration
{
    protected static function booted(): void
    {
        static::addGlobalScope('perte', function (Builder $builder) {
            $builder->where('type', 'perte');
        });

        static::creating(function (Declaration $declaration) {
            $declaration->type = 'perte';
        });
    }
}