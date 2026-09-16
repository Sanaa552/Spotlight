<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\SpotlightResetPassword;
use App\Notifications\SpotlightVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'telephone',
        'photo_path',
        'facebook_id',
        'facebook_avatar_url',
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

    public function photoUrl(): ?string
    {
        if ($this->photo_path) {
            return asset('storage/'.$this->photo_path);
        }

        return $this->facebook_avatar_url;
    }

    public function initiales(): string
    {
        return collect(explode(' ', $this->name))
            ->map(fn ($p) => mb_substr($p, 0, 1))
            ->take(2)
            ->implode('');
    }

    public function isModerateur(): bool
    {
        return $this->role === Role::Moderateur;
    }

    public function isAdministrateur(): bool
    {
        return $this->role === Role::Administrateur;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new SpotlightResetPassword($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new SpotlightVerifyEmail);
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
