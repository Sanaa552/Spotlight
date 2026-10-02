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

        $response = Socialite::driver('facebook')
            ->usingGraphVersion(config('services.meta.graph_version', 'v26.0'))
            ->setScopes(['public_profile', 'email'])
            ->redirect();

        $this->logLoginStart($request, 'login');

        return $response;
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->logLoginCallback($request);

        if ($request->filled('error')) {
            $deleting = $request->user() && $request->session()->has('facebook_delete_user_id');
            $linking = $request->user() && $request->session()->has('facebook_link_user_id');
            $request->session()->forget([
                'facebook_delete_user_id', 'facebook_delete_started_at',
                'facebook_delete_verified_at', 'facebook_delete_verified_user_id',
                'facebook_link_user_id', 'facebook_login_trace',
            ]);

            if ($deleting) {
                return redirect()->route('profile.edit')->withErrors([
                    'facebook' => 'Confirmation Facebook annulée. Votre compte n’a pas été supprimé.',
                ], 'userDeletion');
            }

            if ($linking) {
                return redirect()->route('profile.edit')->withErrors([
                    'facebook' => 'Liaison Facebook annulée. Votre compte reste inchangé.',
                ]);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Connexion Facebook annulée. Vous pouvez réessayer.',
            ]);
        }

        if ($request->user()) {
            if ($request->session()->has('facebook_delete_user_id')) {
                return $this->deleteCallback($request);
            }

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
                    'password_is_local' => false,
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

        $response = Socialite::driver('facebook')
            ->usingGraphVersion(config('services.meta.graph_version', 'v26.0'))
            ->setScopes(['public_profile', 'email'])
            ->redirect();

        $this->logLoginStart($request, 'link');

        return $response;
    }

    public function deleteRedirect(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canConfirmDeletionWithFacebook() && ! $user->is_blocked, 403);

        $callbackHost = parse_url((string) config('services.facebook.redirect'), PHP_URL_HOST);
        if (! $callbackHost || strcasecmp($request->getHost(), $callbackHost) !== 0) {
            return redirect()->route('profile.edit')->withErrors([
                'facebook' => 'Ouvrez Spotlight sur son domaine officiel avant de confirmer la suppression.',
            ], 'userDeletion');
        }

        $request->session()->forget(['facebook_delete_verified_at', 'facebook_delete_verified_user_id']);
        $request->session()->put([
            'facebook_delete_user_id' => $user->id,
            'facebook_delete_started_at' => time(),
        ]);

        $response = Socialite::driver('facebook')
            ->usingGraphVersion(config('services.meta.graph_version', 'v26.0'))
            ->setScopes(['public_profile'])
            ->with(['auth_type' => 'reauthenticate'])
            ->redirect();

        $this->logLoginStart($request, 'delete');

        return $response;
    }

    private function deleteCallback(Request $request): RedirectResponse
    {
        $user = $request->user();
        $expectedUserId = $request->session()->pull('facebook_delete_user_id');
        $startedAt = $request->session()->pull('facebook_delete_started_at');

        if (! $user->canConfirmDeletionWithFacebook() || (int) $expectedUserId !== $user->id
            || ! is_numeric($startedAt) || (int) $startedAt > time()
            || time() - (int) $startedAt > 300) {
            return redirect()->route('profile.edit')->withErrors([
                'facebook' => 'La confirmation Facebook a expiré. Recommencez depuis votre profil.',
            ], 'userDeletion');
        }

        try {
            $facebookUser = Socialite::driver('facebook')
                ->usingGraphVersion(config('services.meta.graph_version', 'v26.0'))
                ->setHttpClient(new Client(['verify' => config('services.meta.ca_bundle') ?: true]))
                ->fields(['id'])
                ->user();
        } catch (Throwable $exception) {
            Log::warning('Confirmation suppression Facebook echouee Spotlight', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return redirect()->route('profile.edit')->withErrors([
                'facebook' => 'Confirmation Facebook annulée ou impossible. Votre compte n’a pas été supprimé.',
            ], 'userDeletion');
        }

        if (! hash_equals((string) $user->facebook_id, (string) $facebookUser->getId())) {
            Log::warning('Identite Facebook differente pour suppression Spotlight', ['user_id' => $user->id]);

            return redirect()->route('profile.edit')->withErrors([
                'facebook' => 'Ce profil Facebook ne correspond pas au compte Spotlight à supprimer.',
            ], 'userDeletion');
        }

        $request->session()->put([
            'facebook_delete_verified_user_id' => $user->id,
            'facebook_delete_verified_at' => time(),
        ]);

        return redirect()->route('profile.edit')->with('facebook_delete_confirmed', true);
    }

    private function logLoginStart(Request $request, string $purpose): void
    {
        if (! config('services.facebook.mobile_diagnostic_enabled')) {
            return;
        }

        $trace = Str::random(12);
        $request->session()->put('facebook_login_trace', $trace);
        Log::info('Diagnostic depart OAuth Facebook Spotlight', [
            'trace' => $trace,
            'purpose' => $purpose,
            'session_state_present' => $request->session()->has('state'),
            'request_secure' => $request->isSecure(),
        ]);
    }

    private function logLoginCallback(Request $request): void
    {
        if (! config('services.facebook.mobile_diagnostic_enabled')) {
            return;
        }

        Log::info('Diagnostic retour OAuth Facebook Spotlight', [
            'trace' => $request->session()->pull('facebook_login_trace'),
            'session_state_present' => $request->session()->has('state'),
            'session_cookie_present' => $request->hasCookie(config('session.cookie')),
            'state_parameter_present' => $request->filled('state'),
            'code_present' => $request->filled('code'),
            'error_present' => $request->filled('error'),
            'request_secure' => $request->isSecure(),
        ]);
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
            $user->password_is_local = true;
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
