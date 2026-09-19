<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Declaration;
use App\Models\Localisation;
use App\Models\PieceJointe;
use App\Models\Rapprochement;
use App\Services\RapprochementNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function create(Request $request): View
    {
        $posteInitial = is_string($request->query('poste'))
            ? mb_substr(trim($request->query('poste')), 0, 255)
            : '';

        $perteId = old('perte_id', $request->query('perte_id'));
        $perteInitiale = is_numeric($perteId) ? Declaration::perteObjetPublique((int) $perteId) : null;

        return view('declarations.create', [
            'posteInitial' => $posteInitial,
            'perteInitiale' => $perteInitiale,
            'defaultType' => ($posteInitial || $perteInitiale) ? 'decouverte' : 'perte',
            'defaultCategory' => ($posteInitial || $perteInitiale) ? 'objet' : '',
        ]);
    }

    /** Déclarer une perte / une découverte (+ joindre photos/pièces justificatives, localisation) */
    public function store(Request $request): RedirectResponse
    {
        $files = collect($request->file('pieces_jointes', []))->filter();
        $declarationPerte = $request->file('declaration_perte');
        $photoPublique = $request->file('photo_publique');
        $preuveDecouverte = $request->file('preuve_decouverte');
        $preuveSignalement = $request->file('preuve_signalement');
        $fileDiagnostics = $files->map(fn ($file) => $this->fileDiagnostic($file))->values()->all();

        Log::info('Tentative de soumission declaration Spotlight', [
            'user_id' => $request->user()->id,
            'type' => $request->input('type'),
            'categorie' => $request->input('categorie'),
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            'gd_loaded' => extension_loaded('gd'),
            'files' => $fileDiagnostics,
            'declaration_perte' => $declarationPerte ? $this->fileDiagnostic($declarationPerte) : null,
            'photo_publique' => $photoPublique ? $this->fileDiagnostic($photoPublique) : null,
            'preuve_decouverte' => $preuveDecouverte ? $this->fileDiagnostic($preuveDecouverte) : null,
            'preuve_signalement' => $preuveSignalement ? $this->fileDiagnostic($preuveSignalement) : null,
        ]);

        if ($photoPublique?->isValid() && $photoPublique->getSize() === 0) {
            Log::warning('Photo publique vide refusee Spotlight', [
                'user_id' => $request->user()->id,
                'filename' => $photoPublique->getClientOriginalName(),
            ]);

            return back()->withInput()->withErrors([
                'photo_publique' => 'La photo reçue est vide. Actualisez la page et sélectionnez à nouveau le fichier original.',
            ]);
        }

        $uploadLimitHint = 'Vérifiez la taille du fichier (limite actuelle du serveur : '.ini_get('upload_max_filesize').' par fichier).';

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:perte,decouverte'],
            'categorie' => ['required', 'in:personne,objet'],
            'description' => ['required', 'string'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'type_perte' => ['nullable', 'string', 'max:255', 'required_if:type,perte'],
            'type_decouverte' => ['nullable', 'string', 'max:255', 'required_if:type,decouverte'],
            'adresse' => ['required', 'string', 'max:255'],
            'poste_prevu' => [
                'nullable', 'string', 'max:255',
                Rule::prohibitedIf(fn () => ! ($request->input('type') === 'decouverte' && $request->input('categorie') === 'objet')),
            ],
            'perte_id' => [
                'nullable', 'integer',
                Rule::prohibitedIf(fn () => ! ($request->input('type') === 'decouverte' && $request->input('categorie') === 'objet')),
            ],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'pieces_jointes' => ['nullable', 'array', 'max:'.config('spotlight.uploads.max_files')],
            'pieces_jointes.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('spotlight.uploads.max_file_kilobytes')],
            'photo_publique' => [
                Rule::requiredIf(fn () => ! ($request->input('type') === 'decouverte' && $request->input('categorie') === 'personne')),
                Rule::prohibitedIf(fn () => $request->input('type') === 'decouverte' && $request->input('categorie') === 'personne'),
                'image', 'mimes:jpg,jpeg,png', 'max:'.config('spotlight.uploads.max_file_kilobytes'),
            ],
            'declaration_perte' => ['required_if:type,perte', 'prohibited_unless:type,perte', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('spotlight.uploads.max_file_kilobytes')],
            'preuve_decouverte' => [
                Rule::requiredIf(fn () => $request->input('type') === 'decouverte' && $request->input('categorie') === 'objet'),
                Rule::prohibitedIf(fn () => ! ($request->input('type') === 'decouverte' && $request->input('categorie') === 'objet')),
                'file', 'mimes:mp4,mov,webm', 'max:'.config('spotlight.uploads.max_video_kilobytes'),
            ],
            'preuve_signalement' => ['nullable', 'prohibited_unless:type,decouverte', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('spotlight.uploads.max_file_kilobytes')],
        ], [
            'pieces_jointes.max' => 'Vous pouvez joindre au maximum :max fichiers.',
            'pieces_jointes.*.file' => 'Une pièce jointe sélectionnée n’est pas un fichier valide.',
            'pieces_jointes.*.mimes' => 'Chaque pièce jointe doit être une image JPG, JPEG, PNG ou un document PDF.',
            'pieces_jointes.*.max' => 'Chaque pièce jointe doit peser au maximum 10 Mo.',
            'pieces_jointes.*.uploaded' => 'Une pièce jointe n’a pas pu être envoyée. '.$uploadLimitHint,
            'photo_publique.required' => 'Ajoutez la photo publique de la personne ou de l’objet concerné.',
            'photo_publique.image' => 'La photo publique doit être une image valide.',
            'photo_publique.mimes' => 'La photo publique doit être au format JPG, JPEG ou PNG.',
            'photo_publique.max' => 'La photo publique doit peser au maximum 10 Mo.',
            'photo_publique.uploaded' => 'La photo publique n’a pas pu être envoyée. '.$uploadLimitHint,
            'declaration_perte.required_if' => 'La preuve de signalement aux autorités est obligatoire pour soumettre une perte.',
            'declaration_perte.prohibited_unless' => 'Cette preuve de signalement ne doit être jointe que pour une perte.',
            'declaration_perte.file' => 'La preuve de signalement sélectionnée n’est pas un fichier valide.',
            'declaration_perte.mimes' => 'La preuve de signalement doit être une image JPG, JPEG, PNG ou un document PDF.',
            'declaration_perte.max' => 'La preuve de signalement doit peser au maximum 10 Mo.',
            'declaration_perte.uploaded' => 'La preuve de signalement n’a pas pu être envoyée. '.$uploadLimitHint,
            'preuve_decouverte.required' => 'Ajoutez une courte vidéo privée de l’objet découvert et du lieu.',
            'preuve_decouverte.mimes' => 'La vidéo doit être au format MP4, MOV ou WebM.',
            'preuve_decouverte.max' => 'La vidéo doit peser au maximum 30 Mo.',
            'preuve_decouverte.uploaded' => 'La vidéo n’a pas pu être envoyée. '.$uploadLimitHint,
            'preuve_signalement.required' => 'Ajoutez un justificatif de remise ou de signalement aux autorités.',
            'preuve_signalement.mimes' => 'Le justificatif doit être une image JPG, PNG ou un PDF.',
            'preuve_signalement.max' => 'Le justificatif doit peser au maximum 10 Mo.',
            'preuve_signalement.uploaded' => 'Le justificatif n’a pas pu être envoyé. '.$uploadLimitHint,
            'photo_publique.prohibited' => 'Ne joignez pas de photo publique pour une personne découverte.',
            'perte_id.integer' => 'Choisissez une annonce de perte valide.',
            'perte_id.prohibited' => 'Une correspondance ne peut être proposée que pour une découverte d’objet.',
        ], [
            'pieces_jointes' => 'pièces jointes',
            'pieces_jointes.*' => 'pièce jointe',
            'photo_publique' => 'photo publique',
            'declaration_perte' => 'preuve de signalement aux autorités',
            'preuve_decouverte' => 'vidéo de découverte',
            'preuve_signalement' => 'justificatif de signalement',
        ]);

        $validator->after(function ($validator) use ($request, $files, $declarationPerte, $photoPublique, $preuveDecouverte, $preuveSignalement) {
            if ($request->filled('perte_id') && is_numeric($request->input('perte_id'))
                && ! Declaration::perteObjetPublique((int) $request->input('perte_id'))) {
                $validator->errors()->add('perte_id', 'Cette annonce de perte n’est plus disponible. Choisissez-en une autre ou déclarez sans correspondance.');
            }
            $totalBytes = $files->sum(fn ($file) => (int) $file->getSize())
                + ($declarationPerte ? (int) $declarationPerte->getSize() : 0)
                + ($photoPublique ? (int) $photoPublique->getSize() : 0)
                + ($preuveDecouverte ? (int) $preuveDecouverte->getSize() : 0)
                + ($preuveSignalement ? (int) $preuveSignalement->getSize() : 0);
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
            $declaration = DB::transaction(function () use ($request, $validated, $declarationPerte, $photoPublique, $preuveDecouverte, $preuveSignalement, &$storedPaths) {
                $declaration = $request->user()->declarations()->create([
                    ...collect($validated)->except(['adresse', 'latitude', 'longitude', 'poste_prevu', 'perte_id', 'pieces_jointes', 'declaration_perte', 'photo_publique', 'preuve_decouverte', 'preuve_signalement'])->toArray(),
                    'statut' => 'en_attente',
                ]);

                Localisation::create([
                    'declaration_id' => $declaration->id,
                    'adresse' => $validated['adresse'],
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'poste_prevu' => $validated['poste_prevu'] ?? null,
                ]);

                if ($photoPublique) {
                    $photoPath = $this->storePublicPhoto($photoPublique);
                    if (! $photoPath) {
                        throw new RuntimeException('Le stockage privé a refusé la photo.');
                    }
                    $storedPaths[] = ['disk' => 'local', 'path' => $photoPath];
                    $declaration->update(['photo_path' => $photoPath]);
                }

                foreach ($request->file('pieces_jointes', []) as $fichier) {
                    $this->storeAttachment($declaration, $fichier, 'piece_jointe', $storedPaths);
                }

                if ($declarationPerte) {
                    $this->storeAttachment($declaration, $declarationPerte, 'declaration_perte', $storedPaths);
                }
                if ($preuveDecouverte) {
                    $this->storeAttachment($declaration, $preuveDecouverte, 'preuve_decouverte', $storedPaths);
                }
                if ($preuveSignalement) {
                    $this->storeAttachment($declaration, $preuveSignalement, 'preuve_signalement', $storedPaths);
                }

                if (! empty($validated['perte_id'])) {
                    Rapprochement::create([
                        'perte_id' => $validated['perte_id'],
                        'decouverte_id' => $declaration->id,
                        'statut' => 'propose',
                    ]);
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

        if ($declaration->rapprochementDecouverte) {
            app(RapprochementNotifier::class)->proposition($declaration->rapprochementDecouverte);
        }

        if ($declaration->type === 'decouverte' && $declaration->categorie === 'objet') {
            return redirect()
                ->route('declarations.show', $declaration)
                ->with('success', 'Déclaration soumise avec succès.');
        }

        return redirect()
            ->route('declarations.index')
            ->with('success', 'Déclaration soumise avec succès.');
    }

    public function publicationStatus(Declaration $declaration): JsonResponse
    {
        $this->authorizeOwner($declaration);

        return response()->json([
            'status' => $declaration->publication_status,
            'declaration_status' => $declaration->statut,
            'delayed' => in_array($declaration->publication_status, ['queued', 'processing'], true)
                && $declaration->updated_at->lt(now()->subMinutes(2)),
            'error' => request()->user()->isCitoyen() ? null : $declaration->publication_error,
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Declaration $declaration): View
    {
        $this->authorizeOwner($declaration);

        $declaration->load('localisation', 'moderateur', 'appNotifications', 'piecesJointes', 'rapprochementDecouverte.perte', 'rapprochementsPerte.decouverte');

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

    public function apercuPieceJointe(PieceJointe $pieceJointe)
    {
        $pieceJointe->loadMissing('declaration');
        $this->authorizeOwner($pieceJointe->declaration);
        abort_unless($pieceJointe->disque === 'local' && Storage::disk('local')->exists($pieceJointe->chemin), 404);

        $mime = Storage::disk('local')->mimeType($pieceJointe->chemin);
        $allowed = ['image/jpeg', 'image/png', 'application/pdf', 'video/mp4', 'video/webm', 'video/quicktime', 'application/mp4'];
        abort_unless(in_array($mime, $allowed, true), 415);

        Log::info('Apercu document prive Spotlight', [
            'piece_jointe_id' => $pieceJointe->id,
            'declaration_id' => $pieceJointe->declaration_id,
            'user_id' => request()->user()->id,
        ]);

        return response()->file(Storage::disk('local')->path($pieceJointe->chemin), [
            'Content-Type' => $mime === 'application/mp4' ? 'video/mp4' : $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function afficherPhotoPrivee(Declaration $declaration)
    {
        $this->authorizeOwner($declaration);
        abort_unless($declaration->photoEnAttente() && Storage::disk('local')->exists($declaration->photo_path), 404);

        return Storage::disk('local')->response(
            $declaration->photo_path,
            null,
            ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']
        );
    }

    public function ajouterPreuveSignalement(Request $request, Declaration $declaration): RedirectResponse
    {
        abort_unless(
            $declaration->user_id === $request->user()->id
                && $declaration->type === 'decouverte'
                && $declaration->statut === 'en_attente',
            403
        );

        if ($declaration->piecesJointes()->where('type_document', 'preuve_signalement')->exists()) {
            return back()->with('warning', 'Un justificatif est déjà joint à ce dossier. Contactez la modération pour toute correction.');
        }

        $validated = $request->validate([
            'preuve_signalement' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('spotlight.uploads.max_file_kilobytes')],
        ], [
            'preuve_signalement.required' => 'Ajoutez le justificatif remis par les autorités.',
            'preuve_signalement.mimes' => 'Le justificatif doit être une image JPG, PNG ou un PDF.',
            'preuve_signalement.max' => 'Le justificatif doit peser au maximum 10 Mo.',
            'preuve_signalement.uploaded' => 'Le justificatif n’a pas pu être envoyé. Vérifiez qu’il ne dépasse pas 10 Mo.',
        ]);

        $storedPaths = [];
        try {
            $this->storeAttachment($declaration, $validated['preuve_signalement'], 'preuve_signalement', $storedPaths);
        } catch (Throwable $exception) {
            foreach ($storedPaths as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
            Log::error('Ajout preuve signalement impossible Spotlight', [
                'declaration_id' => $declaration->id,
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['preuve_signalement' => 'Le justificatif n’a pas pu être enregistré. Réessayez ou contactez l’administrateur.']);
        }

        Log::info('Preuve signalement ajoutee Spotlight', [
            'declaration_id' => $declaration->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Justificatif ajouté. La modération peut maintenant vérifier votre dossier.');
    }

    /** Confirmer restitution : clôture la déclaration */
    public function confirmerRestitution(Declaration $declaration): RedirectResponse
    {
        abort_unless(
            request()->user()->isCitoyen()
                && $declaration->user_id === request()->user()->id
                && $declaration->statut === 'validee'
                && ! ($declaration->type === 'decouverte' && $declaration->categorie === 'personne'),
            403
        );

        if ($declaration->rapprochementDecouverte()->whereIn('statut', ['propose', 'verifie'])->exists()
            || $declaration->rapprochementsPerte()->whereIn('statut', ['propose', 'verifie'])->exists()) {
            return back()->with('warning', 'Une correspondance est en cours. Les deux déclarants doivent confirmer la remise, puis la modération clôturera les dossiers.');
        }

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

    private function storePublicPhoto(UploadedFile $photo): string|false
    {
        if ($photo->getMimeType() !== 'image/png') {
            return $photo->store('photos-en-attente', 'local');
        }

        if (! extension_loaded('gd')) {
            throw new RuntimeException('Extension PHP GD absente : conversion de la photo PNG impossible.');
        }

        $dimensions = @getimagesize($photo->getRealPath());
        if (! $dimensions || $dimensions[0] * $dimensions[1] > 20000000) {
            throw new RuntimeException('Photo PNG illisible ou dimensions trop grandes.');
        }

        $source = @imagecreatefrompng($photo->getRealPath());
        if (! $source) {
            throw new RuntimeException('Décodage de la photo PNG impossible.');
        }

        $scale = min(1, 2048 / max($dimensions[0], $dimensions[1]));
        $width = max(1, (int) round($dimensions[0] * $scale));
        $height = max(1, (int) round($dimensions[1] * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        $temporaryPath = null;

        try {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            if (! imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1])) {
                throw new RuntimeException('Redimensionnement de la photo PNG impossible.');
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'spotlight-photo-');
            if ($temporaryPath === false || ! imagejpeg($canvas, $temporaryPath, 88)) {
                throw new RuntimeException('Conversion de la photo PNG en JPEG impossible.');
            }

            $jpeg = file_get_contents($temporaryPath);
            if ($jpeg === false || $jpeg === '') {
                throw new RuntimeException('La conversion de la photo PNG a produit un JPEG vide.');
            }

            $path = 'photos-en-attente/'.Str::uuid().'.jpg';
            if (! Storage::disk('local')->put($path, $jpeg)) {
                throw new RuntimeException('Le stockage privé a refusé la photo convertie.');
            }

            return $path;
        } finally {
            imagedestroy($canvas);
            imagedestroy($source);
            if ($temporaryPath !== null && $temporaryPath !== false) {
                @unlink($temporaryPath);
            }
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
