<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'telephone',
        'password',
        'role',
        'is_blocked',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_blocked' => 'boolean',
        ];
    }

    // ----- Rôles (équivalent héritage Citoyen / Moderateur / Administrateur) -----

    public function isCitoyen(): bool
    {
        return $this->role === Role::Citoyen;
    }

    public function isModerateur(): bool
    {
        return $this->role === Role::Moderateur;
    }

    public function isAdministrateur(): bool
    {
        return $this->role === Role::Administrateur;
    }

    // ----- Relations -----

    /** Déclarations soumises par ce citoyen */
    public function declarations(): HasMany
    {
        return $this->hasMany(Declaration::class);
    }

    /** Déclarations traitées par ce modérateur */
    public function declarationsTraitees(): HasMany
    {
        return $this->hasMany(Declaration::class, 'moderateur_id');
    }

    /** Notifications reçues par ce citoyen */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    /** Statistiques générées par cet administrateur */
    public function statistiques(): HasMany
    {
        return $this->hasMany(Statistique::class, 'administrateur_id');
    }
}