<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Représentation UML: DeclarationDecouverte extends Declaration.
 * Attribut spécifique: typeDecouverte.
 */
class DeclarationDecouverte extends Declaration
{
    protected static function booted(): void
    {
        static::addGlobalScope('decouverte', function (Builder $builder) {
            $builder->where('type', 'decouverte');
        });

        static::creating(function (Declaration $declaration) {
            $declaration->type = 'decouverte';
        });
    }
}