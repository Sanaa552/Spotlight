<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class UserController extends Controller
{
    /** Gérer comptes : liste des utilisateurs */
    public function index(): View
    {
        $users = User::latest()->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'telephone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in([Role::Citoyen->value, Role::Moderateur->value])],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);

        $passwordWasGenerated = blank($validated['password'] ?? null);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telephone' => $validated['telephone'] ?? null,
            'role' => $validated['role'],
            'password' => Hash::make($validated['password'] ?? Str::random(32)),
        ]);

        Log::info('Utilisateur cree par administrateur Spotlight', [
            'created_user_id' => $user->id,
            'created_role' => $user->role->value,
            'admin_id' => $request->user()->id,
            'password_was_generated' => $passwordWasGenerated,
        ]);

        if (! $passwordWasGenerated) {
            return redirect()
                ->route('admin.users.index')
                ->with('success', 'Compte créé. Le mot de passe provisoire peut être utilisé.');
        }

        try {
            $status = Password::sendResetLink(['email' => $user->email]);

            Log::info('Lien reset envoye apres creation utilisateur Spotlight', [
                'created_user_id' => $user->id,
                'status' => $status,
                'mailer' => config('mail.default'),
            ]);

            if ($status === Password::RESET_LINK_SENT) {
                return redirect()
                    ->route('admin.users.index')
                    ->with('success', 'Compte créé et lien de création du mot de passe envoyé.');
            }

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'Compte créé.')
                ->with('warning', 'Le lien de création du mot de passe n’a pas été envoyé. Vous pourrez le renvoyer depuis la liste.');
        } catch (Throwable $exception) {
            Password::broker()->deleteToken($user);

            Log::error('Echec envoi lien apres creation utilisateur Spotlight', [
                'created_user_id' => $user->id,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'Compte créé.')
                ->with('warning', 'Connexion au service Gmail impossible. Le compte existe bien ; utilisez « Renvoyer le lien » après avoir débloqué SMTP.');
        }
    }

    public function sendResetLink(User $user): RedirectResponse
    {
        if ($user->isAdministrateur()) {
            return back()->withErrors(['user' => 'La réinitialisation web du super administrateur est désactivée.']);
        }

        Password::broker()->deleteToken($user);

        try {
            $status = Password::sendResetLink(['email' => $user->email]);

            Log::info('Renvoi lien mot de passe par administrateur Spotlight', [
                'user_id' => $user->id,
                'admin_id' => request()->user()->id,
                'status' => $status,
                'mailer' => config('mail.default'),
            ]);

            return $status === Password::RESET_LINK_SENT
                ? back()->with('success', 'Lien de création du mot de passe envoyé à '.$user->email.'.')
                : back()->with('warning', 'Le lien n’a pas pu être envoyé : '.__($status));
        } catch (Throwable $exception) {
            Password::broker()->deleteToken($user);

            Log::error('Echec renvoi lien mot de passe par administrateur Spotlight', [
                'user_id' => $user->id,
                'admin_id' => request()->user()->id,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $message = str_contains($exception->getMessage(), 'forbidden by its access permissions')
                ? 'PHP est bloqué par Windows pour l’envoi SMTP. Redémarrez le serveur Spotlight depuis un terminal administrateur, puis réessayez.'
                : 'Connexion au service Gmail impossible. Vérifiez la configuration SMTP, puis réessayez.';

            return back()->with('warning', $message);
        }
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->isAdministrateur()) {
            return back()->withErrors(['user' => 'Le compte super administrateur est protégé et se gère uniquement dans le code.']);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in([Role::Citoyen->value, Role::Moderateur->value])],
            'is_blocked' => ['boolean'],
        ]);

        $before = [
            'role' => $user->role?->value,
            'is_blocked' => (bool) $user->is_blocked,
        ];

        $user->update($validated);

        Log::info('Compte modifie par administrateur Spotlight', [
            'user_id' => $user->id,
            'admin_id' => $request->user()->id,
            'before' => $before,
            'after' => [
                'role' => $user->role?->value,
                'is_blocked' => (bool) $user->is_blocked,
            ],
        ]);

        return back()->with('success', 'Compte mis à jour.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->isAdministrateur()) {
            return back()->withErrors(['user' => 'Le compte super administrateur ne peut pas être supprimé.']);
        }

        $deletedUser = [
            'user_id' => $user->id,
            'role' => $user->role?->value,
        ];

        $user->delete();

        Log::notice('Compte supprime par administrateur Spotlight', [
            ...$deletedUser,
            'admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Compte supprimé.');
    }
}
