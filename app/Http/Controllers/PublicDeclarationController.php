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

        $query = Declaration::query()->with('localisation');

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
}