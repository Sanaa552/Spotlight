<?php

namespace App\Http\Controllers;

use App\Models\Commentaire;
use App\Models\Declaration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    /** Fil d'actualité : toutes les déclarations validées/clôturées, façon réseau social */
    public function index(): View
    {
        $declarations = Declaration::whereIn('statut', ['validee', 'cloturee'])
            ->with(['citoyen', 'localisation', 'piecesJointes', 'commentaires.auteur'])
            ->latest()
            ->paginate(8);

        return view('dashboard', compact('declarations'));
    }

    /** Publier un commentaire sur une déclaration */
    public function commenter(Request $request, Declaration $declaration): RedirectResponse
    {
        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:1000'],
        ]);

        Commentaire::create([
            'declaration_id' => $declaration->id,
            'user_id' => $request->user()->id,
            'contenu' => $validated['contenu'],
        ]);

        return back()->with('success', 'Commentaire publié.')->withFragment('declaration-'.$declaration->id);
    }
}