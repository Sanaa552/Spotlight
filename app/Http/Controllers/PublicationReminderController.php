<?php

namespace App\Http\Controllers;

use App\Jobs\PublishReminder;
use App\Models\Declaration;
use App\Models\PublicationReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PublicationReminderController extends Controller
{
    public function index(): View
    {
        $declarations = Declaration::publique()
            ->whereIn('statut', ['validee', 'cloturee'])
            ->with(['citoyen', 'publicationReminders.auteur'])
            ->latest()->paginate(12);

        return view('moderation.published', compact('declarations'));
    }

    public function store(Request $request, Declaration $declaration): RedirectResponse
    {
        $validated = $request->validate([
            'selection' => ['required', Rule::in(['facebook', 'instagram', 'both'])],
        ]);
        abort_unless($this->canRemind($declaration), 404);

        $channels = $validated['selection'] === 'both'
            ? ['facebook', 'instagram']
            : [$validated['selection']];
        $lock = Cache::lock('rappel-declaration-'.$declaration->id, 30);
        if (! $lock->get()) {
            return back()->with('warning', 'Un rappel de ce dossier est déjà en préparation.');
        }

        try {
            if (PublicationReminder::query()->where('declaration_id', $declaration->id)
                ->whereIn('channel', $channels)->whereIn('status', ['queued', 'processing'])->exists()) {
                return back()->with('warning', 'Un rappel est déjà en cours sur un des réseaux choisis.');
            }

            DB::transaction(function () use ($declaration, $channels, $request) {
                foreach ($channels as $channel) {
                    $reminder = PublicationReminder::create([
                        'declaration_id' => $declaration->id,
                        'user_id' => $request->user()->id,
                        'channel' => $channel,
                        'status' => 'queued',
                    ]);
                    PublishReminder::dispatch($reminder->id)->onConnection('database');
                }
            });

            Log::notice('Rappel Meta demande Spotlight', [
                'declaration_id' => $declaration->id,
                'actor_id' => $request->user()->id,
                'channels' => $channels,
            ]);

            return back()->with('success', 'Rappel lancé. Son résultat apparaîtra dans l’historique.');
        } catch (Throwable $exception) {
            Log::error('Mise en file du rappel impossible Spotlight', [
                'declaration_id' => $declaration->id,
                'actor_id' => $request->user()->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('warning', 'Le rappel n’a pas pu être lancé. Contactez l’administrateur.');
        } finally {
            $lock->release();
        }
    }

    public function retry(Request $request, PublicationReminder $reminder): RedirectResponse
    {
        abort_unless($this->canRemind($reminder->declaration), 404);
        $lock = Cache::lock('rappel-declaration-'.$reminder->declaration_id, 30);
        if (! $lock->get()) {
            return back()->with('warning', 'Un rappel de ce dossier est déjà en préparation.');
        }

        try {
            if (PublicationReminder::query()
                ->where('declaration_id', $reminder->declaration_id)
                ->where('channel', $reminder->channel)
                ->whereIn('status', ['queued', 'processing'])->exists()) {
                return back()->with('warning', 'Un rappel est déjà en cours sur ce réseau.');
            }

            $updated = PublicationReminder::query()->whereKey($reminder->id)
                ->where('status', 'failed')->update(['status' => 'queued', 'error' => null]);
            if (! $updated) {
                return back()->with('warning', 'Ce rappel est déjà en cours ou terminé.');
            }

            PublishReminder::dispatch($reminder->id)->onConnection('database');
            Log::notice('Rappel Meta relance Spotlight', [
                'reminder_id' => $reminder->id,
                'actor_id' => $request->user()->id,
            ]);

            return back()->with('success', 'Nouvelle tentative lancée sur le réseau concerné.');
        } catch (Throwable $exception) {
            $reminder->update([
                'status' => 'failed',
                'error' => 'La nouvelle tentative n’a pas pu être mise en file.',
            ]);
            Log::error('Relance rappel Meta impossible Spotlight', [
                'reminder_id' => $reminder->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('warning', 'La nouvelle tentative n’a pas pu être lancée.');
        } finally {
            $lock->release();
        }
    }

    private function canRemind(Declaration $declaration): bool
    {
        return in_array($declaration->statut, ['validee', 'cloturee'], true)
            && Declaration::publique()->whereKey($declaration->id)->exists()
            && filled($declaration->photo_path)
            && Storage::disk('public')->exists($declaration->photo_path);
    }
}
