<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'telephone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'password' => Hash::make($request->password),
            'role' => Role::Citoyen,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        Log::info('Inscription citoyen Spotlight', [
            'user_id' => $user->id,
        ]);

        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            Log::error('Echec envoi verification email apres inscription Spotlight', [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('verification.notice')
                ->withErrors(['email' => 'Votre compte a été créé, mais le courriel de vérification n’a pas pu être envoyé. Utilisez le bouton de renvoi.']);
        }

        return redirect()
            ->route('verification.notice')
            ->with('status', 'verification-link-sent');
    }
}
