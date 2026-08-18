<?php

namespace App\Http\Controllers;

use App\Models\Declaration;
use App\Models\Localisation;
use App\Models\PieceJointe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $validated = $request->validate([
            'type' => ['required', 'in:perte,decouverte'],
            'categorie' => ['required', 'string', 'max:255'], // personne | objet
            'description' => ['required', 'string'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'type_perte' => ['nullable', 'string', 'required_if:type,perte'],
            'type_decouverte' => ['nullable', 'string', 'required_if:type,decouverte'],
            'adresse' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'pieces_jointes' => ['nullable', 'array', 'max:5'],
            'pieces_jointes.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $declaration = $request->user()->declarations()->create([
            ...collect($validated)->except(['adresse', 'latitude', 'longitude', 'pieces_jointes'])->toArray(),
            'statut' => 'en_attente',
        ]);

        // Relation 1-1 avec Localisation (potentiellement enrichie par l'API de géolocalisation)
        Localisation::create([
            'declaration_id' => $declaration->id,
            'adresse' => $validated['adresse'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        // Relation 1-N avec PieceJointe (plusieurs fichiers possibles)
        foreach ($request->file('pieces_jointes', []) as $fichier) {
            $chemin = $fichier->store('declarations', 'public');

            PieceJointe::create([
                'declaration_id' => $declaration->id,
                'chemin' => $chemin,
                'nom_original' => $fichier->getClientOriginalName(),
                'type_mime' => $fichier->getMimeType(),
                'taille' => $fichier->getSize(),
            ]);
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

    /** Confirmer restitution : clôture la déclaration */
    public function confirmerRestitution(Declaration $declaration): RedirectResponse
    {
        $this->authorizeOwner($declaration);

        $declaration->cloturer();

        return back()->with('success', 'Restitution confirmée, déclaration clôturée.');
    }

    private function authorizeOwner(Declaration $declaration): void
    {
        if ($declaration->user_id !== request()->user()->id) {
            abort(403);
        }
    }
}