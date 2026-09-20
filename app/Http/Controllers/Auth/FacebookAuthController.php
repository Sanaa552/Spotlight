<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
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
            $facebookUser = Socialite::driver('facebook')
                ->setHttpClient(new Client(['verify' => config('services.meta.ca_bundle') ?: true]))
                ->user();
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

        $email = Str::lower(trim($facebookUser->getEmail()));
        $user = User::where('facebook_id', $facebookUser->getId())
            ->orWhere('email', $email)
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

            if (filled($user->facebook_id) && $user->facebook_id !== $facebookUser->getId()) {
                Log::warning('Compte deja lie a un autre profil Facebook Spotlight', ['user_id' => $user->id]);

                return redirect()->route('login')
                    ->withErrors(['email' => 'Ce compte est déjà lié à un autre profil Facebook. Connectez-vous avec votre mot de passe ou contactez l’administrateur.']);
            }

            if ($user->is_blocked) {
                Log::notice('Connexion Facebook refusee pour compte bloque Spotlight', ['user_id' => $user->id]);

                return redirect()->route('login')
                    ->withErrors(['email' => 'Votre compte a été bloqué.']);
            }

            $user->forceFill([
                'facebook_id' => $facebookUser->getId(),
                'facebook_avatar_url' => $facebookUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at
                    ?? (Str::lower($user->email) === $email ? now() : null),
            ])->save();
        } else {
            $user = new User([
                'name' => $facebookUser->getName() ?: $facebookUser->getNickname() ?: 'Utilisateur Facebook',
                'email' => $email,
                'facebook_id' => $facebookUser->getId(),
                'facebook_avatar_url' => $facebookUser->getAvatar(),
                'password' => Hash::make(Str::random(32)),
                'role' => Role::Citoyen,
            ]);
            $user->email_verified_at = now();
            $user->save();
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
