<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class EmailVerificationAddressController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isCitoyen() && ! $user->hasVerifiedEmail() && ! $user->is_blocked, 403);

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ]);

        if ($validated['email'] === $user->email) {
            return back()->withErrors(['email' => 'Saisissez une adresse différente de l’adresse actuelle.']);
        }

        Password::broker()->deleteToken($user);

        $user->forceFill([
            'email' => $validated['email'],
            'email_verified_at' => null,
        ])->save();

        Log::info('Adresse email corrigee avant verification Spotlight', ['user_id' => $user->id]);

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::error('Echec envoi verification apres correction email Spotlight', [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('verification.notice')
                ->withErrors(['email' => 'La nouvelle adresse est enregistrée, mais le lien n’a pas pu être envoyé. Réessayez avec « Renvoyer le lien ».']);
        }

        return redirect()
            ->route('verification.notice')
            ->with('status', 'verification-address-updated');
    }
}
