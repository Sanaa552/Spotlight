<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CompleteFacebookProfileController extends Controller
{
    public function edit(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        abort_unless($user->isCitoyen() && filled($user->facebook_id) && ! $user->is_blocked, 403);

        if (! $user->needsFacebookProfileCompletion()) {
            return redirect()->route('dashboard');
        }

        return view('auth.complete-facebook-profile', ['user' => $user]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isCitoyen() && filled($user->facebook_id) && ! $user->is_blocked, 403);

        if (! $user->needsFacebookProfileCompletion()) {
            return redirect()->route('dashboard');
        }

        $phoneMissing = blank($user->telephone);
        $emailMissing = blank($user->email);
        if ($phoneMissing) {
            $request->merge(['telephone' => preg_replace('/[\s().-]+/', '', (string) $request->input('telephone'))]);
        }
        if ($emailMissing) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }

        $rules = [];
        if ($phoneMissing) {
            $rules['telephone'] = ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'];
        }
        if ($emailMissing) {
            $rules['email'] = ['required', 'string', 'email', 'max:255', Rule::unique(User::class)];
        }

        $validated = $request->validate($rules, [
            'telephone.regex' => 'Saisissez un numéro international, par exemple +237690000000.',
        ]);

        if ($phoneMissing) {
            $user->telephone = $validated['telephone'];
        }
        if ($emailMissing) {
            $user->email = $validated['email'];
            $user->email_source = User::EMAIL_SOURCE_MANUAL;
            $user->email_verified_at = null;
        }
        $user->save();

        if ($emailMissing) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                Log::error('Envoi verification profil Facebook echoue Spotlight', [
                    'user_id' => $user->id,
                    'exception' => $exception::class,
                ]);

                return redirect()->route('verification.notice')->withErrors([
                    'email' => 'Votre profil est enregistré, mais le lien de vérification n’a pas pu être envoyé. Utilisez « Renvoyer le lien ».',
                ]);
            }

            return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
