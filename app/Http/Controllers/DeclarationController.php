<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Declaration;
use App\Models\Localisation;
use App\Models\PieceJointe;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class DeclarationController extends Controller
{
    /** Suivre déclaration : liste des déclarations du citoyen connecté */
    public function index(Request $request): View
    {
        $declarations = $request->user()
            ->declarations()
            ->with('localisation')
            ->latest()
            ->paginate(10);

        return view('declarations.index', compact('declarations'));
    }

    public function create(): View
    {
        return view('declarations.create');
    }

    /** Déclarer une perte / une découverte (+ joindre photos/pièces justificatives, localisation) */
    public function store(Request $request): RedirectResponse
    {
        $files = collect($request->file('pieces_jointes', []))->filter();
        $declarationPerte = $request->file('declaration_perte');
        $photoPublique = $request->file('photo_publique');
        $fileDiagnostics = $files->map(fn ($file) => $this->fileDiagnostic($file))->values()->all();

        Log::info('Tentative de soumission declaration Spotlight', [
            'user_id' => $request->user()->id,
            'type' => $request->input('type'),
            'categorie' => $request->input('categorie'),
            'files' => $fileDiagnostics,
            'declaration_perte' => $declarationPerte ? $this->fileDiagnostic($declarationPerte) : null,
            'photo_publique' => $photoPublique ? $this->fileDiagnostic($photoPublique) : null,
        ]);

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:perte,decouverte'],
            'categorie' => ['required', 'in:personne,objet'],
            'description' => ['required', 'string'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'type_perte' => ['nullable', 'string', 'max:255', 'required_if:type,perte'],
            'type_decouverte' => ['nullable', 'string', 'max:255', 'required_if:type,decouverte'],
            'adresse' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'pieces_jointes' => ['nullable', 'array', 'max:'.config('spotlight.uploads.max_files')],
            'pieces_jointes.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('spotlight.uploads.max_file_kilobytes')],
            'photo_publique' => ['required', 'image', 'mimes:jpg,jpeg', 'max:'.config('spotlight.uploads.max_file_kilobytes')],
            'declaration_perte' => ['required_if:type,perte', 'prohibited_unless:type,perte', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('spotlight.uploads.max_file_kilobytes')],
        ], [
            'pieces_jointes.max' => 'Vous pouvez joindre au maximum :max fichiers.',
            'pieces_jointes.*.file' => 'Une pièce jointe sélectionnée n’est pas un fichier valide.',
            'pieces_jointes.*.mimes' => 'Chaque pièce jointe doit être une image JPG, JPEG, PNG ou un document PDF.',
            'pieces_jointes.*.max' => 'Chaque pièce jointe doit peser au maximum 10 Mo.',
            'pieces_jointes.*.uploaded' => 'Une pièce jointe n’a pas pu être envoyée. Vérifiez qu’elle ne dépasse pas 10 Mo.',
            'photo_publique.required' => 'Ajoutez la photo publique de la personne ou de l’objet concerné.',
            'photo_publique.image' => 'La photo publique doit être une image valide.',
            'photo_publique.mimes' => 'La photo publique doit être au format JPG ou JPEG.',
            'photo_publique.max' => 'La photo publique doit peser au maximum 10 Mo.',
            'photo_publique.uploaded' => 'La photo publique n’a pas pu être envoyée. Vérifiez qu’elle ne dépasse pas 10 Mo.',
            'declaration_perte.required_if' => 'Le document de déclaration de perte est obligatoire pour signaler une perte.',
            'declaration_perte.prohibited_unless' => 'Le document de déclaration de perte ne doit être joint que pour une perte.',
            'declaration_perte.file' => 'La déclaration de perte sélectionnée n’est pas un fichier valide.',
            'declaration_perte.mimes' => 'La déclaration de perte doit être une image JPG, JPEG, PNG ou un document PDF.',
            'declaration_perte.max' => 'La déclaration de perte doit peser au maximum 10 Mo.',
            'declaration_perte.uploaded' => 'La déclaration de perte n’a pas pu être envoyée. Vérifiez qu’elle ne dépasse pas 10 Mo.',
        ], [
            'pieces_jointes' => 'pièces jointes',
            'pieces_jointes.*' => 'pièce jointe',
            'photo_publique' => 'photo publique',
            'declaration_perte' => 'déclaration de perte',
        ]);

        $validator->after(function ($validator) use ($files, $declarationPerte, $photoPublique) {
            $totalBytes = $files->sum(fn ($file) => (int) $file->getSize())
                + ($declarationPerte ? (int) $declarationPerte->getSize() : 0)
                + ($photoPublique ? (int) $photoPublique->getSize() : 0);
            $maxTotalBytes = (int) config('spotlight.uploads.max_total_kilobytes') * 1024;

            if ($totalBytes > $maxTotalBytes) {
                $validator->errors()->add('pieces_jointes', 'L’ensemble des fichiers ne doit pas dépasser 60 Mo.');
            }
        });

        if ($validator->fails()) {
            Log::warning('Declaration refusee par validation Spotlight', [
                'user_id' => $request->user()->id,
                'errors' => $validator->errors()->toArray(),
                'files' => $fileDiagnostics,
                'declaration_perte' => $declarationPerte ? $this->fileDiagnostic($declarationPerte) : null,
                'photo_publique' => $photoPublique ? $this->fileDiagnostic($photoPublique) : null,
            ]);

            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $storedPaths = [];

        try {
            $declaration = DB::transaction(function () use ($request, $validated, $declarationPerte, $photoPublique, &$storedPaths) {
                $declaration = $request->user()->declarations()->create([
                    ...collect($validated)->except(['adresse', 'latitude', 'longitude', 'pieces_jointes', 'declaration_perte', 'photo_publique'])->toArray(),
                    'statut' => 'en_attente',
                ]);

                Localisation::create([
                    'declaration_id' => $declaration->id,
                    'adresse' => $validated['adresse'],
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                ]);

                $photoPath = $photoPublique->store('photos-publiques', 'public');
                if (! $photoPath) {
                    throw new RuntimeException('Le stockage public a refusé la photo.');
                }
                $storedPaths[] = ['disk' => 'public', 'path' => $photoPath];
                $declaration->update(['photo_path' => $photoPath]);

                foreach ($request->file('pieces_jointes', []) as $fichier) {
                    $this->storeAttachment($declaration, $fichier, 'piece_jointe', $storedPaths);
                }

                if ($declarationPerte) {
                    $this->storeAttachment($declaration, $declarationPerte, 'declaration_perte', $storedPaths);
                }

                return $declaration;
            });
        } catch (Throwable $exception) {
            collect($storedPaths)
                ->groupBy('disk')
                ->each(fn ($paths, $disk) => Storage::disk($disk)->delete($paths->pluck('path')->all()));

            Log::error('Echec enregistrement declaration Spotlight', [
                'user_id' => $request->user()->id,
                'type' => $request->input('type'),
                'categorie' => $request->input('categorie'),
                'stored_paths_cleaned' => $storedPaths,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['declaration' => 'La déclaration n’a pas pu être enregistrée. Réessayez et, si le problème persiste, contactez l’administrateur.']);
        }

        Log::info('Declaration soumise Spotlight', [
            'declaration_id' => $declaration->id,
            'user_id' => $request->user()->id,
            'type' => $declaration->type,
            'categorie' => $declaration->categorie,
            'pieces_jointes_count' => $declaration->piecesJointes()->count(),
            'has_declaration_perte' => $declaration->declarationPerte()->exists(),
            'has_photo_publique' => filled($declaration->photo_path),
            'has_coordinates' => filled($validated['latitude'] ?? null) && filled($validated['longitude'] ?? null),
        ]);

        if ($declaration->type === 'decouverte') {
            return redirect()
                ->route('declarations.show', $declaration)
                ->with('success', 'Déclaration soumise avec succès.')
                ->with('proposer_carte', true);
        }

        return redirect()
            ->route('declarations.index')
            ->with('success', 'Déclaration soumise avec succès.');
    }

    public function show(Declaration $declaration): View
    {
        $this->authorizeOwner($declaration);

        $declaration->load('localisation', 'moderateur', 'appNotifications', 'piecesJointes');

        return view('declarations.show', compact('declaration'));
    }

    public function telechargerPieceJointe(PieceJointe $pieceJointe)
    {
        $pieceJointe->loadMissing('declaration');
        $this->authorizeOwner($pieceJointe->declaration);

        Log::info('Telechargement document declaration Spotlight', [
            'piece_jointe_id' => $pieceJointe->id,
            'declaration_id' => $pieceJointe->declaration_id,
            'user_id' => request()->user()->id,
            'type_document' => $pieceJointe->type_document,
        ]);

        abort_unless(Storage::disk($pieceJointe->disque)->exists($pieceJointe->chemin), 404);

        return Storage::disk($pieceJointe->disque)->download($pieceJointe->chemin, $pieceJointe->nom_original);
    }

    /** Confirmer restitution : clôture la déclaration */
    public function confirmerRestitution(Declaration $declaration): RedirectResponse
    {
        abort_unless(
            request()->user()->isCitoyen()
                && $declaration->user_id === request()->user()->id
                && $declaration->statut === 'validee',
            403
        );

        $declaration->cloturer();

        Log::info('Restitution confirmee Spotlight', [
            'declaration_id' => $declaration->id,
            'user_id' => request()->user()->id,
        ]);

        return back()->with('success', 'Restitution confirmée, déclaration clôturée.');
    }

    private function authorizeOwner(Declaration $declaration): void
    {
        $user = request()->user();

        // Administrateur et modérateur peuvent voir toutes les déclarations
        if (in_array($user->role, [Role::Administrateur, Role::Moderateur], true)) {
            return;
        }

        // Le citoyen peut uniquement voir ses propres déclarations
        if ($declaration->user_id !== $user->id) {
            abort(403);
        }
    }

    private function storeAttachment(
        Declaration $declaration,
        UploadedFile $file,
        string $documentType,
        array &$storedPaths,
    ): void {
        $disk = 'local';
        $path = $file->store('declarations-privees', $disk);

        if (! $path) {
            throw new RuntimeException("Le stockage {$disk} a refusé un fichier.");
        }

        $storedPaths[] = ['disk' => $disk, 'path' => $path];

        PieceJointe::create([
            'declaration_id' => $declaration->id,
            'type_document' => $documentType,
            'disque' => $disk,
            'chemin' => $path,
            'nom_original' => $file->getClientOriginalName(),
            'type_mime' => $file->getMimeType(),
            'taille' => $file->getSize(),
        ]);
    }

    private function fileDiagnostic(UploadedFile $file): array
    {
        return [
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getClientMimeType(),
            'upload_error' => $file->getError(),
        ];
    }
}
