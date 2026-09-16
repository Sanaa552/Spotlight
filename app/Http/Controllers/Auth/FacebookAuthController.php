<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class FacebookAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (blank(config('services.facebook.client_id')) || blank(config('services.facebook.client_secret'))) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'La connexion Facebook n’est pas encore configurée.']);
        }

        return Socialite::driver('facebook')
            ->scopes(['email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $facebookUser = Socialite::driver('facebook')->user();
        } catch (Throwable $exception) {
            Log::warning('Connexion Facebook echouee Spotlight', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Connexion Facebook impossible. Réessayez ou utilisez email/mot de passe.']);
        }

        if (blank($facebookUser->getEmail())) {
            Log::warning('Connexion Facebook sans email Spotlight', [
                'facebook_id' => $facebookUser->getId(),
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Facebook n’a pas fourni d’adresse email. Autorisez l’email ou utilisez l’inscription classique.']);
        }

        $user = User::where('facebook_id', $facebookUser->getId())
            ->orWhere('email', $facebookUser->getEmail())
            ->first();

        if ($user) {
            if (! $user->isCitoyen()) {
                Log::notice('Connexion Facebook refusee pour role interne Spotlight', [
                    'user_id' => $user->id,
                    'role' => $user->role->value,
                    'facebook_id' => $facebookUser->getId(),
                ]);

                return redirect()
                    ->route('login')
                    ->withErrors(['email' => 'La connexion Facebook est réservée aux comptes citoyens.']);
            }

            $user->forceFill([
                'facebook_id' => $facebookUser->getId(),
                'facebook_avatar_url' => $facebookUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $facebookUser->getName() ?: $facebookUser->getNickname() ?: 'Utilisateur Facebook',
                'email' => $facebookUser->getEmail(),
                'facebook_id' => $facebookUser->getId(),
                'facebook_avatar_url' => $facebookUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(32)),
                'role' => Role::Citoyen,
            ]);
        }

        if ($user->is_blocked) {
            Log::notice('Connexion Facebook refusee pour compte bloque Spotlight', [
                'user_id' => $user->id,
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Votre compte a été bloqué.']);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        Log::info('Connexion Facebook reussie Spotlight', [
            'user_id' => $user->id,
            'facebook_id' => $facebookUser->getId(),
            'has_avatar' => filled($facebookUser->getAvatar()),
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
