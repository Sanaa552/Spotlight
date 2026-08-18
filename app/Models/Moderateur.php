<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;

/**
 * Représentation UML: Modérateur extends Utilisateur.
 */
class Moderateur extends User
{
    protected static function booted(): void
    {
        static::addGlobalScope('moderateur', function (Builder $builder) {
            $builder->where('role', Role::Moderateur);
        });

        static::creating(function (User $user) {
            $user->role = Role::Moderateur;
        });
    }

    // Opérations UML: verifierDocument(), validerDeclaration(), rejeterDeclaration(),
    // notifierCitoyen(), bloquerUtilisateur()

    public function validerDeclaration(Declaration $declaration): Declaration
    {
        $declaration->update([
            'statut' => 'validee',
            'moderateur_id' => $this->id,
        ]);

        return $declaration;
    }

    public function rejeterDeclaration(Declaration $declaration, string $motif): Declaration
    {
        $declaration->update([
            'statut' => 'rejetee',
            'motif_rejet' => $motif,
            'moderateur_id' => $this->id,
        ]);

        return $declaration;
    }

    public function bloquerUtilisateur(User $user): User
    {
        $user->update(['is_blocked' => true]);

        return $user;
    }
}
