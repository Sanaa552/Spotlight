<?php

namespace App\Http\Controllers;

use App\Models\Declaration;
use App\Models\Rapprochement;
use App\Services\RapprochementNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RapprochementController extends Controller
{
    public function pertes(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'decouverte' => ['nullable', 'integer'],
        ]);
        $decouverte = null;
        if (isset($validated['decouverte'])) {
            $decouverte = Declaration::query()->findOrFail($validated['decouverte']);
            abort_unless($decouverte->user_id === $request->user()->id
                && $decouverte->type === 'decouverte' && $decouverte->categorie === 'objet'
                && in_array($decouverte->statut, ['en_attente', 'validee'], true), 403);
        }

        $query = Declaration::publique()->where('type', 'perte')
            ->where('categorie', 'objet')->where('statut', 'validee');
        if ($term = trim($validated['q'] ?? '')) {
            $query->where(fn ($query) => $query->where('type_perte', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')
                ->orWhere('lieu', 'like', '%'.$term.'%'));
        }

        return view('declarations.pertes-objets', [
            'pertes' => $query->latest()->paginate(12)->withQueryString(),
            'decouverte' => $decouverte,
            'q' => $term ?? '',
        ]);
    }

    public function proposer(Request $request, Declaration $declaration, RapprochementNotifier $notifier): RedirectResponse
    {
        abort_unless($declaration->user_id === $request->user()->id
            && $declaration->type === 'decouverte' && $declaration->categorie === 'objet'
            && in_array($declaration->statut, ['en_attente', 'validee'], true), 403);

        $validated = $request->validate(['perte_id' => ['required', 'integer']]);
        $perte = Declaration::perteObjetPublique($validated['perte_id']);
        if (! $perte) {
            return back()->with('warning', 'Cette annonce de perte n’est plus disponible. Cherchez une autre annonce ou laissez la découverte sans correspondance.');
        }

        $rapprochement = DB::transaction(function () use ($declaration, $perte) {
            $existant = Rapprochement::query()->where('decouverte_id', $declaration->id)->lockForUpdate()->first();
            if ($existant && $existant->statut !== 'rejete') {
                return null;
            }

            return Rapprochement::updateOrCreate(['decouverte_id' => $declaration->id], [
                'perte_id' => $perte->id,
                'statut' => 'propose',
                'moderateur_id' => null,
                'verifie_at' => null,
                'proprietaire_confirme_at' => null,
                'decouvreur_confirme_at' => null,
                'restitue_at' => null,
            ]);
        });

        if (! $rapprochement) {
            return back()->with('warning', 'Une correspondance est déjà en cours pour cette découverte. Contactez la modération pour la corriger.');
        }

        Log::info('Correspondance proposee Spotlight', ['rapprochement_id' => $rapprochement->id, 'user_id' => $request->user()->id]);
        $notifier->proposition($rapprochement);

        return redirect()->route('declarations.show', $declaration)
            ->with('success', 'Correspondance proposée. La modération vérifiera les deux dossiers avant tout contact.');
    }

    public function verifier(Request $request, Rapprochement $rapprochement, RapprochementNotifier $notifier): RedirectResponse
    {
        $updated = DB::transaction(function () use ($rapprochement, $request) {
            $match = Rapprochement::query()->whereKey($rapprochement->id)->lockForUpdate()->firstOrFail();
            $perte = Declaration::query()->whereKey($match->perte_id)->lockForUpdate()->firstOrFail();
            $decouverte = Declaration::query()->whereKey($match->decouverte_id)->lockForUpdate()->firstOrFail();
            if ($match->statut !== 'propose' || $perte->statut !== 'validee' || $decouverte->statut !== 'validee'
                || $perte->categorie !== 'objet' || $decouverte->categorie !== 'objet') {
                return false;
            }
            if (Rapprochement::query()->where('perte_id', $perte->id)->where('statut', 'verifie')->exists()) {
                return false;
            }
            $match->update(['statut' => 'verifie', 'moderateur_id' => $request->user()->id, 'verifie_at' => now()]);
            return true;
        });

        if (! $updated) {
            return back()->with('warning', 'Vérification impossible : les deux déclarations doivent d’abord être validées et encore actives.');
        }

        Log::info('Correspondance verifiee Spotlight', ['rapprochement_id' => $rapprochement->id, 'moderateur_id' => $request->user()->id]);
        $notifier->decision($rapprochement->fresh(['perte.citoyen', 'decouverte.citoyen']), true);

        return back()->with('success', 'Correspondance vérifiée. Les déclarants ont été avertis sans échange automatique de coordonnées.');
    }

    public function rejeter(Request $request, Rapprochement $rapprochement, RapprochementNotifier $notifier): RedirectResponse
    {
        $updated = Rapprochement::query()->whereKey($rapprochement->id)->where('statut', 'propose')
            ->update(['statut' => 'rejete', 'moderateur_id' => $request->user()->id]);
        if (! $updated) {
            return back()->with('warning', 'Cette proposition a déjà été traitée.');
        }

        Log::info('Correspondance rejetee Spotlight', ['rapprochement_id' => $rapprochement->id, 'moderateur_id' => $request->user()->id]);
        $notifier->decision($rapprochement->fresh(['decouverte.citoyen']), false);

        return back()->with('success', 'Proposition refusée. La découverte reste suivie séparément.');
    }

    public function confirmer(Request $request, Rapprochement $rapprochement): RedirectResponse
    {
        $owner = $rapprochement->perte->user_id === $request->user()->id;
        $finder = $rapprochement->decouverte->user_id === $request->user()->id;
        abort_unless($owner || $finder, 403);

        $updated = Rapprochement::query()->whereKey($rapprochement->id)->where('statut', 'verifie')->update([
            $owner ? 'proprietaire_confirme_at' : 'decouvreur_confirme_at' => now(),
        ]);
        if (! $updated) {
            return back()->with('warning', 'La remise ne peut pas encore être confirmée. Attendez la vérification de la modération.');
        }

        Log::info('Remise confirmee par declarant Spotlight', ['rapprochement_id' => $rapprochement->id, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Votre confirmation a été enregistrée. La modération clôturera les dossiers après contrôle.');
    }

    public function finaliser(Request $request, Rapprochement $rapprochement, RapprochementNotifier $notifier): RedirectResponse
    {
        $updated = DB::transaction(function () use ($rapprochement, $request) {
            $match = Rapprochement::query()->whereKey($rapprochement->id)->lockForUpdate()->firstOrFail();
            $perte = Declaration::query()->whereKey($match->perte_id)->lockForUpdate()->firstOrFail();
            $decouverte = Declaration::query()->whereKey($match->decouverte_id)->lockForUpdate()->firstOrFail();
            if ($match->statut !== 'verifie' || ! $match->proprietaire_confirme_at || ! $match->decouvreur_confirme_at
                || $perte->statut !== 'validee' || $decouverte->statut !== 'validee') {
                return false;
            }
            $perte->cloturer();
            $decouverte->cloturer();
            $match->update(['statut' => 'restitue', 'restitue_at' => now(), 'moderateur_id' => $request->user()->id]);
            return true;
        });

        if (! $updated) {
            return back()->with('warning', 'Les deux confirmations et les deux dossiers validés sont nécessaires avant la clôture.');
        }

        Log::info('Restitution rapprochee finalisee Spotlight', ['rapprochement_id' => $rapprochement->id, 'moderateur_id' => $request->user()->id]);
        $notifier->restitution($rapprochement->fresh(['perte.citoyen', 'decouverte.citoyen']));

        return back()->with('success', 'Restitution confirmée. Les deux dossiers sont déplacés dans les restitutions.');
    }

}
