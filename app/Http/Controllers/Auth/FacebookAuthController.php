<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class FacebookAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (blank(config('services.facebook.client_id')) || blank(config('services.facebook.client_secret'))) {
            return redirect()->route('login')
                ->withErrors(['email' => 'La connexion Facebook n’est pas encore configurée.']);
        }

        $callback = parse_url((string) config('services.facebook.redirect'));
        if (! is_array($callback) || empty($callback['host']) || ! in_array($callback['scheme'] ?? null, ['http', 'https'], true)) {
            Log::error('Callback Facebook invalide Spotlight');

            return redirect()->route('login')
                ->withErrors(['email' => 'La connexion Facebook est mal configurée. Contactez l’administrateur.']);
        }

        // The OAuth state must be saved and read on the same browser origin.
        if (strcasecmp($request->getHost(), $callback['host']) !== 0) {
            $origin = $callback['scheme'].'://'.$callback['host']
                .(isset($callback['port']) ? ':'.$callback['port'] : '');

            return redirect()->away($origin.'/auth/facebook');
        }

        return Socialite::driver('facebook')
            ->usingGraphVersion(config('services.meta.graph_version', 'v26.0'))
            ->setScopes(['public_profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return $request->session()->has('facebook_link_user_id')
                ? $this->linkCallback($request)
                : redirect()->route('dashboard');
        }

        $diagnostics = [
            'has_state_parameter' => $request->filled('state'),
            'has_session_state' => $request->session()->has('state'),
            'has_session_cookie' => $request->hasCookie(config('session.cookie')),
            'request_host' => $request->getHost(),
            'callback_host' => parse_url((string) config('services.facebook.redirect'), PHP_URL_HOST),
            'request_secure' => $request->isSecure(),
            'session_driver' => config('session.driver'),
        ];

        try {
            $facebookUser = Socialite::driver('facebook')
                ->usingGraphVersion(config('services.meta.graph_version', 'v26.0'))
                ->setHttpClient(new Client(['verify' => config('services.meta.ca_bundle') ?: true]))
                ->fields(['id', 'name', 'email', 'picture.type(large)'])
                ->user();
        } catch (InvalidStateException $exception) {
            Log::warning('Etat OAuth Facebook invalide Spotlight', $diagnostics);

            return redirect()->route('login')->withErrors([
                'email' => 'La session de connexion Facebook a expiré ou changé de domaine. Ouvrez Spotlight à son adresse officielle, puis réessayez.',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Connexion Facebook echouee Spotlight', [
                ...$diagnostics,
                ...$this->safeProviderError($exception),
            ]);

            return redirect()->route('login')
                ->withErrors(['email' => 'Connexion Facebook impossible. Réessayez ou utilisez email/mot de passe.']);
        }

        $facebookId = trim((string) $facebookUser->getId());
        if ($facebookId === '' || strlen($facebookId) > 255) {
            Log::warning('Identifiant Facebook absent Spotlight');

            return redirect()->route('login')->withErrors([
                'email' => 'Facebook n’a pas fourni votre identifiant. Réessayez.',
            ]);
        }

        $this->diagnoseFacebookEmail($facebookUser);

        $email = $facebookUser->getEmail();
        $facebookEmailReceived = is_string($email) && trim($email) !== '';
        $email = is_string($email) ? Str::lower(trim($email)) : null;
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

        try {
            $user = User::where('facebook_id', $facebookId)->first();

            if ($user) {
                if (! $user->isCitoyen() || $user->is_blocked) {
                    Log::notice('Connexion Facebook refusee pour compte interne ou bloque Spotlight', ['user_id' => $user->id]);

                    return redirect()->route('login')->withErrors(['email' => 'Ce compte ne peut pas se connecter avec Facebook.']);
                }

                if (blank($user->email) && $email) {
                    if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
                        return $this->emailConflict();
                    }
                    $user->email = $email;
                    $user->email_verified_at = now();
                    $user->email_source = User::EMAIL_SOURCE_FACEBOOK;
                } elseif ($email && Str::lower((string) $user->email) === $email
                    && $user->email_source !== User::EMAIL_SOURCE_MANUAL) {
                    $user->email_source = User::EMAIL_SOURCE_FACEBOOK;
                    if (! $user->hasVerifiedEmail()) {
                        $user->email_verified_at = now();
                    }
                }

                $user->facebook_avatar_url = $facebookUser->getAvatar();
                $user->save();
            } else {
                if ($email && User::where('email', $email)->exists()) {
                    return $this->emailConflict();
                }

                $user = User::create([
                    'name' => $facebookUser->getName() ?: $facebookUser->getNickname() ?: 'Utilisateur Facebook',
                    'email' => $email,
                    'email_source' => $email ? User::EMAIL_SOURCE_FACEBOOK : null,
                    'facebook_id' => $facebookId,
                    'facebook_avatar_url' => $facebookUser->getAvatar(),
                    'password' => Hash::make(Str::random(32)),
                    'role' => Role::Citoyen,
                ]);
                if ($email) {
                    $user->markEmailAsVerified();
                }
            }
        } catch (QueryException $exception) {
            Log::error('Enregistrement connexion Facebook echoue Spotlight', [
                'exception' => $exception::class,
                'sqlstate' => $exception->errorInfo[0] ?? null,
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'Le compte n’a pas pu être enregistré. Réessayez ou contactez l’administrateur.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        Log::info('Connexion Facebook reussie Spotlight', [
            'user_id' => $user->id,
            'facebook_email_received' => $facebookEmailReceived,
            'facebook_email_usable' => filled($email),
            'email_stored' => filled($user->email),
        ]);

        return $user->needsFacebookProfileCompletion()
            ? redirect()->route('facebook.profile.edit')
            : redirect()->intended(route('dashboard', absolute: false));
    }

    public function linkRedirect(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isCitoyen() && ! $user->is_blocked && blank($user->facebook_id), 403);

        if (blank(config('services.facebook.client_id')) || blank(config('services.facebook.client_secret'))) {
            return redirect()->route('profile.edit')->withErrors([
                'facebook' => 'La connexion Facebook n’est pas encore configurée.',
            ]);
        }

        $callbackHost = parse_url((string) config('services.facebook.redirect'), PHP_URL_HOST);
        if (! $callbackHost || strcasecmp($request->getHost(), $callbackHost) !== 0) {
            return redirect()->route('profile.edit')->withErrors([
                'facebook' => 'Ouvrez Spotlight sur son domaine officiel avant de lier Facebook.',
            ]);
        }

        $request->session()->put('facebook_link_user_id', $user->id);

        return Socialite::driver('facebook')
            ->usingGraphVersion(config('services.meta.graph_version', 'v26.0'))
            ->setScopes(['public_profile', 'email'])
            ->redirect();
    }

    public function linkCallback(Request $request): RedirectResponse
    {
        $user = $request->user();
        $linkUserId = $request->session()->pull('facebook_link_user_id');
        abort_unless($user->isCitoyen() && ! $user->is_blocked && blank($user->facebook_id)
            && (int) $linkUserId === $user->id, 403);

        try {
            $facebookUser = Socialite::driver('facebook')
                ->usingGraphVersion(config('services.meta.graph_version', 'v26.0'))
                ->setHttpClient(new Client(['verify' => config('services.meta.ca_bundle') ?: true]))
                ->fields(['id', 'name', 'email', 'picture.type(large)'])
                ->user();
        } catch (InvalidStateException $exception) {
            Log::warning('Etat OAuth liaison Facebook invalide Spotlight', [
                'user_id' => $user->id,
                'has_session_cookie' => $request->hasCookie(config('session.cookie')),
                'request_host' => $request->getHost(),
            ]);

            return redirect()->route('profile.edit')->withErrors(['facebook' => 'La session Facebook a expiré. Réessayez.']);
        } catch (Throwable $exception) {
            Log::warning('Liaison Facebook echouee Spotlight', [
                'user_id' => $user->id,
                ...$this->safeProviderError($exception),
            ]);

            return redirect()->route('profile.edit')->withErrors(['facebook' => 'Liaison Facebook impossible. Réessayez.']);
        }

        $facebookId = trim((string) $facebookUser->getId());
        if ($facebookId === '' || strlen($facebookId) > 255
            || User::where('facebook_id', $facebookId)->exists()) {
            return redirect()->route('profile.edit')->withErrors([
                'facebook' => 'Ce profil Facebook est déjà lié à un autre compte ou ne fournit pas d’identifiant valide.',
            ]);
        }

        try {
            $user->facebook_id = $facebookId;
            $user->facebook_avatar_url = $facebookUser->getAvatar();
            $user->save();
        } catch (QueryException $exception) {
            Log::warning('Liaison Facebook concurrente refusee Spotlight', [
                'user_id' => $user->id,
                'sqlstate' => $exception->errorInfo[0] ?? null,
            ]);

            return redirect()->route('profile.edit')->withErrors([
                'facebook' => 'Ce profil Facebook a déjà été lié. Réessayez avec un autre compte.',
            ]);
        }

        Log::info('Compte Facebook lie volontairement Spotlight', ['user_id' => $user->id]);

        return redirect()->route('profile.edit')->with('status', 'facebook-linked');
    }

    private function emailConflict(): RedirectResponse
    {
        Log::notice('Connexion Facebook refusee pour email deja utilise Spotlight');

        return redirect()->route('login')->withErrors([
            'email' => 'Cette adresse e-mail appartient déjà à un compte Spotlight. Pour éviter une association non autorisée, connectez-vous avec son mot de passe. Votre compte existant reste intact.',
        ]);
    }

    private function diagnoseFacebookEmail(\Laravel\Socialite\Contracts\User $facebookUser): void
    {
        if (! config('services.facebook.email_diagnostic_enabled')) {
            return;
        }

        $raw = $facebookUser->getRaw();
        $rawEmail = $raw['email'] ?? null;
        $socialiteEmail = $facebookUser->getEmail();
        $permission = 'unavailable';

        try {
            $token = $facebookUser->token;
            if (is_string($token) && $token !== '') {
                $response = Http::withToken($token)
                    ->withOptions(['verify' => config('services.meta.ca_bundle') ?: true])
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get('https://graph.facebook.com/'.config('services.meta.graph_version', 'v26.0').'/me/permissions');

                if ($response->successful() && is_array($response->json('data'))) {
                    $permission = 'absent';
                    foreach ($response->json('data') as $entry) {
                        if (is_array($entry) && ($entry['permission'] ?? null) === 'email') {
                            $permission = in_array($entry['status'] ?? null, ['granted', 'declined'], true)
                                ? $entry['status'] : 'unavailable';
                            break;
                        }
                    }
                }
            }
        } catch (Throwable) {
            // This temporary diagnostic must never interrupt Facebook Login.
        }

        Log::info('Diagnostic email Facebook Spotlight', [
            'permission_email' => $permission,
            'graph_email_present' => array_key_exists('email', $raw),
            'graph_email_nonempty' => is_string($rawEmail) && trim($rawEmail) !== '',
            'socialite_email_usable' => is_string($socialiteEmail)
                && (bool) filter_var(trim($socialiteEmail), FILTER_VALIDATE_EMAIL),
        ]);
    }

    private function safeProviderError(Throwable $exception): array
    {
        $response = $exception instanceof BadResponseException ? $exception->getResponse() : null;
        $payload = $response ? json_decode((string) $response->getBody(), true) : null;

        return [
            'exception' => $exception::class,
            'http_status' => $response?->getStatusCode(),
            'meta_error_code' => data_get($payload, 'error.code'),
            'meta_error_subcode' => data_get($payload, 'error.error_subcode'),
        ];
    }
}
