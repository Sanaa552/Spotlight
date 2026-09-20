<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AppNotification;
use App\Models\Commentaire;
use App\Models\Declaration;
use App\Models\User;
use App\Notifications\CommentReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class FeedController extends Controller
{
    /** Fil d'actualité : toutes les déclarations validées/clôturées, façon réseau social */
    public function index(): View
    {
        $declarations = Declaration::publique()->where('statut', 'validee')
            ->with(['citoyen', 'localisation', 'commentaires.auteur'])
            ->latest()
            ->paginate(8);

        return view('dashboard', compact('declarations'));
    }

    /** Publier un commentaire sur une déclaration */
    public function commenter(Request $request, Declaration $declaration): RedirectResponse
    {
        abort_unless(
            Declaration::publique()->whereKey($declaration->id)
                ->whereIn('statut', ['validee', 'cloturee'])->exists(),
            404
        );

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('commentaires', 'id')
                ->where('declaration_id', $declaration->id)],
        ]);

        $comment = Commentaire::create([
            'declaration_id' => $declaration->id,
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'contenu' => $validated['contenu'],
        ]);

        Log::info('Commentaire publie Spotlight', [
            'declaration_id' => $declaration->id,
            'user_id' => $request->user()->id,
        ]);

        $recipientIds = [$declaration->user_id];
        if ($request->user()->isCitoyen()) {
            $recipientIds = array_merge($recipientIds, User::query()
                ->whereIn('role', [Role::Moderateur->value, Role::Administrateur->value])
                ->where('is_blocked', false)
                ->pluck('id')->all());
        }
        if ($comment->parent_id) {
            $recipientIds[] = $comment->parent->user_id;
        }

        $recipients = User::query()
            ->whereIn('id', array_values(array_diff(array_unique($recipientIds), [$request->user()->id])))
            ->where('is_blocked', false)
            ->get();

        foreach ($recipients as $recipient) {
            try {
                AppNotification::create([
                    'user_id' => $recipient->id,
                    'declaration_id' => $declaration->id,
                    'message' => ($comment->parent_id ? 'Nouvelle réponse' : 'Nouvelle information')." de {$request->user()->name} sur la déclaration #{$declaration->id} : ".Str::limit($comment->contenu, 140),
                    'date_envoi' => now(),
                    'canal' => 'app',
                ]);
                $recipient->notify(new CommentReceived(
                    $declaration->id,
                    $request->user()->name,
                    $comment->contenu,
                    (bool) $comment->parent_id,
                ));
            } catch (Throwable $exception) {
                Log::error('Notification de commentaire impossible Spotlight', [
                    'declaration_id' => $declaration->id,
                    'commentaire_id' => $comment->id,
                    'recipient_id' => $recipient->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Commentaire publié.')->withFragment('declaration-'.$declaration->id);
    }

    public function supprimerCommentaire(Request $request, Commentaire $commentaire): RedirectResponse
    {
        $declarationId = $commentaire->declaration_id;
        $commentaireId = $commentaire->id;
        $commentaire->delete();

        Log::notice('Commentaire supprime par moderation Spotlight', [
            'declaration_id' => $declarationId,
            'commentaire_id' => $commentaireId,
            'moderateur_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Commentaire supprimé.')->withFragment('declaration-'.$declarationId);
    }
}
