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

        // ----- Données pour les graphiques -----
        $mois = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $evolutionPertes = $mois->map(function ($date) {
            return Declaration::where('type', 'perte')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        });

        $evolutionDecouvertes = $mois->map(function ($date) {
            return Declaration::where('type', 'decouverte')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        });

        $evolutionRestitutions = $mois->map(function ($date) {
            return Declaration::where('statut', 'cloturee')
                ->whereYear('cloturee_at', $date->year)
                ->whereMonth('cloturee_at', $date->month)
                ->count();
        });

        $repartitionStatuts = [
            'en_attente' => Declaration::where('statut', 'en_attente')->count(),
            'validee' => Declaration::where('statut', 'validee')->count(),
            'rejetee' => Declaration::where('statut', 'rejetee')->count(),
            'cloturee' => Declaration::where('statut', 'cloturee')->count(),
        ];

        return view('admin.statistiques.index', [
            'statistiques' => $statistiques,
            'chartLabels' => $mois->map(fn ($d) => $d->translatedFormat('M Y'))->toArray(),
            'chartPertes' => $evolutionPertes->toArray(),
            'chartDecouvertes' => $evolutionDecouvertes->toArray(),
            'chartRestitutions' => $evolutionRestitutions->toArray(),
            'repartitionStatuts' => $repartitionStatuts,
        ]);
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