<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->string('email')->lower()->toString();
        $user = User::where('email', $email)->first();

        if ($user?->isAdministrateur()) {
            Log::notice('Reset web refuse pour super administrateur Spotlight', [
                'user_id' => $user->id,
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Le mot de passe du super administrateur se gère uniquement par le seed ou la ligne de commande.']);
        }

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (Throwable $exception) {
            if ($user) {
                Password::broker()->deleteToken($user);
            }

            Log::error('Echec connexion mail pour reinitialisation Spotlight', [
                'email' => $email,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $message = str_contains($exception->getMessage(), 'forbidden by its access permissions')
                ? 'PHP est bloqué par Windows pour l’envoi Gmail. Redémarrez le serveur Spotlight depuis un terminal administrateur, puis réessayez.'
                : 'Le service d’envoi Gmail est temporairement inaccessible. Vérifiez la configuration SMTP, puis réessayez.';

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $message]);
        }

        Log::info('Demande de reinitialisation mot de passe Spotlight', [
            'email' => $email,
            'status' => $status,
            'mailer' => config('mail.default'),
        ]);

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', 'Lien de réinitialisation envoyé. Vérifiez votre email ou les logs si MAIL_MAILER=log.')
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
