<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;

/**
 * Représentation UML: Administrateur extends Utilisateur.
 */
class Administrateur extends User
{
    protected static function booted(): void
    {
        static::addGlobalScope('administrateur', function (Builder $builder) {
            $builder->where('role', Role::Administrateur);
        });

        static::creating(function (User $user) {
            $user->role = Role::Administrateur;
        });
    }

    // Opérations UML: Gerer_compte(), Generer_statistique()

    public function genererStatistique(string $type, array $donnees = []): Statistique
    {
        return $this->statistiques()->create([
            'type' => $type,
            'date_generation' => now(),
            'donnees' => $donnees,
        ]);
    }
}