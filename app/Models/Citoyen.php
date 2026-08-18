<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;

/**
 * Représentation UML: Citoyen extends Utilisateur.
 * Implémentée en single-table inheritance sur `users.role`.
 */
class Citoyen extends User
{
    protected static function booted(): void
    {
        static::addGlobalScope('citoyen', function (Builder $builder) {
            $builder->where('role', Role::Citoyen);
        });

        static::creating(function (User $user) {
            $user->role = Role::Citoyen;
        });
    }

    // Opérations UML: declarer(), rechercher_declaration(), rechercherCorrespondance()

    public function declarer(array $data): Declaration
    {
        return $this->declarations()->create($data);
    }
}