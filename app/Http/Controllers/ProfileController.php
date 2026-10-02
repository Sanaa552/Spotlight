<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Commentaire;
use App\Models\Declaration;
use App\Models\PublicationReminder;
use App\Models\Rapprochement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'facebookDeletionConfirmed' => (int) $request->session()->get('facebook_delete_verified_user_id') === $request->user()->id
                && (int) $request->session()->get('facebook_delete_verified_at', 0) <= time()
                && time() - (int) $request->session()->get('facebook_delete_verified_at', 0) <= 300,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse{
        $validated = $request->validated();

        if ($request->hasFile('photo')) {
            if ($request->user()->photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($request->user()->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('profils', 'public');
        }
        unset($validated['photo']);

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_source = User::EMAIL_SOURCE_MANUAL;
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()->isAdministrateur()) {
            return back()->withErrors([
                'user' => 'Le compte super administrateur ne peut pas être supprimé depuis l’application.',
            ], 'userDeletion');
        }

        $user = $request->user();

        if ($request->input('confirmation_method') === 'facebook' && $user->canConfirmDeletionWithFacebook()) {
            $request->validateWithBag('userDeletion', ['confirm_delete' => ['accepted']]);

            if ((int) $request->session()->get('facebook_delete_verified_user_id') !== $user->id
                || (int) $request->session()->get('facebook_delete_verified_at', 0) > time()
                || time() - (int) $request->session()->get('facebook_delete_verified_at', 0) > 300) {
                return back()->withErrors([
                    'facebook' => 'La confirmation Facebook a expiré. Recommencez avant de supprimer le compte.',
                ], 'userDeletion');
            }
        } elseif (in_array($request->input('confirmation_method'), [null, 'password'], true)
            && $user->canConfirmDeletionWithPassword()) {
            $request->validateWithBag('userDeletion', [
                'password' => ['required', 'current_password'],
            ], [
                'password.required' => 'Saisissez votre mot de passe Spotlight actuel.',
                'password.current_password' => 'Le mot de passe Spotlight actuel est incorrect.',
            ]);
        } else {
            return back()->withErrors([
                'user' => 'Confirmez votre identité avant de supprimer le compte.',
            ], 'userDeletion');
        }

        if ($user->declarations()->exists() || $user->declarationsTraitees()->exists()
            || Commentaire::where('user_id', $user->id)->exists()
            || PublicationReminder::where('user_id', $user->id)->exists()
            || Declaration::where('poste_verifie_par', $user->id)->exists()
            || Rapprochement::where('moderateur_id', $user->id)->exists()) {
            return back()->withErrors([
                'user' => 'Ce compte possède des dossiers ou des interventions liés à des avis. Contactez l’administration : leur conservation et les publications Meta doivent être traitées avant toute suppression.',
            ], 'userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
