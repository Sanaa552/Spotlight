<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Declaration;
use App\Models\Statistique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatistiqueController extends Controller
{
    public function index(): View
    {
        $statistiques = Statistique::latest('date_generation')->paginate(20);

        return view('admin.statistiques.index', compact('statistiques'));
    }

    /** Générer statistiques (calcul local, éventuellement enrichi par API statistique externe) */
    public function generer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:255'],
        ]);

        $donnees = match ($validated['type']) {
            'declarations_par_statut' => Declaration::query()
                ->selectRaw('statut, count(*) as total')
                ->groupBy('statut')
                ->pluck('total', 'statut'),

            'declarations_par_type' => Declaration::query()
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type'),

            'taux_restitution' => [
                'total' => $total = Declaration::count(),
                'cloturees' => $cloturees = Declaration::where('statut', 'cloturee')->count(),
                'taux' => $total > 0 ? round($cloturees / $total * 100, 2) : 0,
            ],

            default => [],
        };

        $statistique = $request->user()->statistiques()->create([
            'type' => $validated['type'],
            'date_generation' => now(),
            'donnees' => $donnees,
        ]);

        return redirect()
            ->route('admin.statistiques.index')
            ->with('success', "Statistique « {$statistique->type} » générée.");
    }
}