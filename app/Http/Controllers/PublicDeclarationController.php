<?php

namespace App\Http\Controllers;

use App\Models\Declaration;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicDeclarationController extends Controller
{
    /** Page publique : Pertes / Découvertes / Restitutions (visiteurs non connectés) */
    public function index(Request $request): View
    {
        $onglet = $request->query('onglet', 'pertes');

        $query = Declaration::publique()->with('localisation');

        $declarations = match ($onglet) {
            'decouvertes' => $query->where('type', 'decouverte')->where('statut', 'validee'),
            'restitutions' => $query->where('statut', 'cloturee'),
            default => $query->where('type', 'perte')->where('statut', 'validee'),
        };

        $declarations = $declarations->latest()->paginate(9)->withQueryString();

        return view('public.declarations.index', [
            'declarations' => $declarations,
            'onglet' => $onglet,
        ]);
    }

    public function show(Declaration $declaration): View
    {
        abort_unless(
            Declaration::publique()->whereKey($declaration->id)
                ->whereIn('statut', ['validee', 'cloturee'])->exists(),
            404
        );

        $declaration->load('commentaires.auteur');

        return view('public.declarations.show', compact('declaration'));
    }
}
