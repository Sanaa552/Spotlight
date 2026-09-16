<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = config('spotlight.super_admin.email');
        $admin = User::firstOrNew(['email' => $email]);
        $isNewAdmin = ! $admin->exists;
        $temporaryPassword = null;

        if ($isNewAdmin) {
            $temporaryPassword = config('spotlight.super_admin.password') ?: Str::password(20);
            $admin->password = Hash::make($temporaryPassword);
        }

        $admin->fill([
            'name' => config('spotlight.super_admin.name'),
            'telephone' => config('spotlight.super_admin.telephone'),
            'role' => Role::Administrateur,
            'is_blocked' => false,
        ]);
        $admin->email_verified_at ??= now();
        $admin->save();

        if ($isNewAdmin) {
            $this->command?->info('Super administrateur Spotlight créé.');
            $this->command?->line('Email : '.$admin->email);
            $this->command?->warn('Mot de passe temporaire : '.$temporaryPassword);
            $this->command?->warn('Conservez-le puis changez-le après la première connexion.');
        } else {
            $this->command?->info('Le super administrateur Spotlight existe déjà. Son mot de passe est inchangé.');
        }
    }
}
