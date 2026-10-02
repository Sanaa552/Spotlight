<?php

namespace App\Http\Controllers;

use App\Jobs\PublishReminder;
use App\Models\Declaration;
use App\Models\PublicationReminder;
use App\Models\Rapprochement;
use App\Services\RapprochementNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class RapprochementController extends Controller
{
    public function pertes(Request $request): View|JsonResponse
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

        $query = Declaration::disponiblePourCorrespondance();
        if ($term = trim($validated['q'] ?? '')) {
            $query->where(fn ($query) => $query->where('type_perte', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')
                ->orWhere('lieu', 'like', '%'.$term.'%'));
        }

        $pertes = $query->latest()->paginate(12)->withQueryString();

        if ($request->query('format') === 'json') {
            return response()->json([
                'pertes' => $pertes->getCollection()->map(fn ($perte) => [
                    'id' => $perte->id,
                    'titre' => $perte->type_perte ?: 'Objet perdu',
                    'description' => $perte->description,
                    'lieu' => $perte->lieu,
                    'photo' => $perte->photoUrl(),
                    'url' => route('public.declarations.show', $perte),
                ])->values(),
                'page' => $pertes->currentPage(),
                'derniere_page' => $pertes->lastPage(),
            ]);
        }

        return view('declarations.pertes-objets', [
            'pertes' => $pertes,
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
        $perte = Declaration::perteObjetDisponible($validated['perte_id']);
        if (! $perte) {
            return back()->with('warning', 'Cette perte est déjà localisée ou n’est plus disponible pour une correspondance. Cherchez une autre annonce ou laissez la découverte sans correspondance.');
        }

        $rapprochement = DB::transaction(function () use ($declaration, $perte) {
            $decouverte = Declaration::query()->whereKey($declaration->id)->lockForUpdate()->firstOrFail();
            if (! in_array($decouverte->statut, ['en_attente', 'validee'], true)
                || ($decouverte->statut === 'en_attente' && in_array($decouverte->publication_status, ['queued', 'processing'], true))) {
                return 'publication_en_cours';
            }
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

        if ($rapprochement === 'publication_en_cours') {
            return back()->with('warning', 'La publication de cette découverte est déjà en cours. Attendez son résultat avant de proposer une correspondance.');
        }
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
        $request->validate(['correspondance_verifiee' => ['accepted']], [
            'correspondance_verifiee.accepted' => 'Confirmez la comparaison des deux dossiers et des preuves privées.',
        ]);

        $updated = DB::transaction(function () use ($rapprochement, $request) {
            $match = Rapprochement::query()->whereKey($rapprochement->id)->lockForUpdate()->firstOrFail();
            $perte = Declaration::query()->whereKey($match->perte_id)->lockForUpdate()->firstOrFail();
            $decouverte = Declaration::query()->whereKey($match->decouverte_id)->lockForUpdate()->firstOrFail();
            if ($match->statut !== 'propose' || $perte->statut !== 'validee'
                || ! in_array($decouverte->statut, ['en_attente', 'validee'], true)
                || in_array($decouverte->publication_status, ['queued', 'processing'], true)
                || $perte->type !== 'perte' || $decouverte->type !== 'decouverte'
                || $perte->categorie !== 'objet' || $decouverte->categorie !== 'objet') {
                return false;
            }
            if ($decouverte->statut === 'en_attente') {
                foreach (['preuve_decouverte', 'preuve_signalement'] as $type) {
                    $document = $decouverte->piecesJointes()->where('type_document', $type)->first();
                    if (! $document || ! Storage::disk($document->disque)->exists($document->chemin)) {
                        return false;
                    }
                }
            }
            if (Rapprochement::query()->where('perte_id', $perte->id)->where('statut', 'verifie')->exists()) {
                return false;
            }
            $match->update(['statut' => 'verifie', 'moderateur_id' => $request->user()->id, 'verifie_at' => now()]);
            return true;
        });

        if (! $updated) {
            return back()->with('warning', 'Correspondance non vérifiée. La perte doit être publiée, la découverte active et ses preuves privées disponibles. Vérifiez aussi qu’aucune publication n’est en cours.');
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

    public function confirmer(Request $request, Rapprochement $rapprochement, RapprochementNotifier $notifier): RedirectResponse
    {
        $owner = $rapprochement->perte->user_id === $request->user()->id;
        $finder = $rapprochement->decouverte->user_id === $request->user()->id;
        abort_unless($owner || $finder, 403);

        if ($rapprochement->perte->statut !== 'validee' || $rapprochement->decouverte->statut !== 'validee') {
            return back()->with('warning', 'Attendez la publication des deux déclarations avant de confirmer la remise.');
        }

        $confirmationColumn = $owner ? 'proprietaire_confirme_at' : 'decouvreur_confirme_at';
        $updated = Rapprochement::query()->whereKey($rapprochement->id)->where('statut', 'verifie')
            ->whereNull($confirmationColumn)->update([
            $owner ? 'proprietaire_confirme_at' : 'decouvreur_confirme_at' => now(),
        ]);
        if (! $updated) {
            return back()->with('warning', 'Cette remise a déjà été confirmée ou attend encore la vérification de la modération.');
        }

        Log::info('Remise confirmee par declarant Spotlight', ['rapprochement_id' => $rapprochement->id, 'user_id' => $request->user()->id]);
        if ($owner) {
            $notifier->remiseDeclareeParProprietaire($rapprochement);
        } else {
            $notifier->remiseDeclareeParDecouvreur($rapprochement->fresh(['perte.citoyen', 'decouverte.citoyen']));
        }

        return back()->with('success', $owner
            ? 'Votre confirmation a été enregistrée. La modération clôturera les dossiers après contrôle.'
            : 'Votre signalement de remise au propriétaire a été enregistré. Le propriétaire et la modération ont été informés ; la restitution reste à vérifier.');
    }

    public function finaliser(Request $request, Rapprochement $rapprochement, RapprochementNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate([
            'restitution_verifiee' => ['accepted'],
            'restitution_note' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'restitution_verifiee.accepted' => 'Confirmez le contrôle de la remise effective avant de clôturer.',
            'restitution_note.required' => 'Consignez la vérification de la restitution.',
        ]);

        try {
            $updated = DB::transaction(function () use ($rapprochement, $request, $validated) {
                $match = Rapprochement::query()->whereKey($rapprochement->id)->lockForUpdate()->firstOrFail();
                $perte = Declaration::query()->whereKey($match->perte_id)->lockForUpdate()->firstOrFail();
                $decouverte = Declaration::query()->whereKey($match->decouverte_id)->lockForUpdate()->firstOrFail();
                if ($match->statut !== 'verifie' || ! $match->proprietaire_confirme_at
                    || $perte->type !== 'perte' || $perte->categorie !== 'objet'
                    || $decouverte->type !== 'decouverte' || $decouverte->categorie !== 'objet'
                    || $perte->statut !== 'validee' || $decouverte->statut !== 'validee') {
                    return false;
                }
                $perte->cloturer();
                $decouverte->cloturer();
                $match->update([
                    'statut' => 'restitue',
                    'restitue_at' => now(),
                    'moderateur_id' => $request->user()->id,
                    'restitution_note' => $validated['restitution_note'],
                ]);
                foreach (['facebook', 'instagram'] as $channel) {
                    $notice = PublicationReminder::create([
                        'declaration_id' => $perte->id,
                        'user_id' => $request->user()->id,
                        'kind' => 'restitution',
                        'channel' => $channel,
                        'status' => 'queued',
                    ]);
                    PublishReminder::dispatch($notice->id)->onConnection('database');
                }
                return true;
            });
        } catch (Throwable $exception) {
            Log::error('Cloture restitution impossible Spotlight', [
                'rapprochement_id' => $rapprochement->id,
                'moderateur_id' => $request->user()->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('warning', 'La restitution n’a pas pu être clôturée. Contactez l’administrateur.');
        }

        if (! $updated) {
            return back()->with('warning', 'La confirmation du propriétaire et les deux dossiers validés sont nécessaires avant la clôture.');
        }

        Log::info('Restitution rapprochee finalisee Spotlight', ['rapprochement_id' => $rapprochement->id, 'moderateur_id' => $request->user()->id]);
        $notifier->restitution($rapprochement->fresh(['perte.citoyen', 'decouverte.citoyen']));

        return back()->with('success', 'Restitution confirmée. Les dossiers sont clôturés ; les avis Facebook et Instagram sont en cours de publication.');
    }

}
