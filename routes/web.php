<?php

use App\Http\Controllers\Admin\StatistiqueController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CommissariatController;
use App\Http\Controllers\DeclarationController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ModerateurController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDeclarationController;
use App\Http\Controllers\PublicationReminderController;
use App\Http\Controllers\RapprochementController;
use Illuminate\Support\Facades\Route;

Route::get('/declarations-publiques', [PublicDeclarationController::class, 'index'])->name('public.declarations.index');
Route::get('/declarations-publiques/{declaration}', [PublicDeclarationController::class, 'show'])->name('public.declarations.show');
Route::view('/a-propos', 'public.about')->name('public.about');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [FeedController::class, 'index'])->middleware(['auth', 'verified', 'not_blocked'])->name('dashboard');

Route::middleware(['auth', 'verified', 'not_blocked'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{appNotification}/lue', [NotificationController::class, 'marquerLue'])
        ->name('notifications.marquer-lue');
});

Route::post('/declarations/{declaration}/commenter', [FeedController::class, 'commenter'])
    ->middleware(['auth', 'verified', 'not_blocked'])
    ->name('declarations.commenter');

Route::get('/pieces-jointes/{pieceJointe}/telecharger', [DeclarationController::class, 'telechargerPieceJointe'])
    ->middleware(['auth', 'verified', 'not_blocked'])
    ->name('pieces-jointes.telecharger');

Route::get('/pieces-jointes/{pieceJointe}/apercu', [DeclarationController::class, 'apercuPieceJointe'])
    ->middleware(['auth', 'verified', 'not_blocked'])
    ->name('pieces-jointes.apercu');

Route::get('/declarations/{declaration}/photo-privee', [DeclarationController::class, 'afficherPhotoPrivee'])
    ->middleware(['auth', 'verified', 'not_blocked'])
    ->name('declarations.photo-privee');

Route::get('/declarations/{declaration}/statut-publication', [DeclarationController::class, 'publicationStatus'])
    ->middleware(['auth', 'verified', 'not_blocked'])
    ->name('declarations.publication-status');

Route::middleware(['auth', 'not_blocked'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// ---------- Citoyen ----------
Route::middleware(['auth', 'verified', 'not_blocked', 'role:citoyen'])->group(function () {
    Route::get('/pertes-objets', [RapprochementController::class, 'pertes'])->name('rapprochements.pertes');
    Route::post('/declarations/{declaration}/correspondance', [RapprochementController::class, 'proposer'])
        ->middleware('throttle:6,1')->name('rapprochements.proposer');
    Route::post('/rapprochements/{rapprochement}/confirmer-remise', [RapprochementController::class, 'confirmer'])
        ->name('rapprochements.confirmer');
    Route::get('/commissariats', [CommissariatController::class, 'rechercher'])
        ->middleware('throttle:6,1')->name('commissariats.rechercher');
    Route::get('/commissariats/adresse', [CommissariatController::class, 'adresse'])
        ->middleware('throttle:12,1')->name('commissariats.adresse');
    Route::get('/declarations', [DeclarationController::class, 'index'])->name('declarations.index');
    Route::get('/declarations/creer', [DeclarationController::class, 'create'])->name('declarations.create');
    Route::post('/declarations', [DeclarationController::class, 'store'])->name('declarations.store');
    Route::post('/declarations/{declaration}/preuve-signalement', [DeclarationController::class, 'ajouterPreuveSignalement'])
        ->name('declarations.preuve-signalement.store');
    Route::get('/declarations/{declaration}', [DeclarationController::class, 'show'])->name('declarations.show');
    Route::post('/declarations/{declaration}/confirmer-restitution', [DeclarationController::class, 'confirmerRestitution'])
        ->name('declarations.confirmer-restitution');

    Route::get('/declarations/{declaration}/commissariats', [CommissariatController::class, 'proches'])
        ->middleware('throttle:6,1')->name('declarations.commissariats');
});

// ---------- Modérateur ----------
Route::middleware(['auth', 'verified', 'not_blocked', 'role:moderateur,administrateur'])->prefix('moderation')->name('moderation.')->group(function () {
    Route::post('/rapprochements/{rapprochement}/verifier', [RapprochementController::class, 'verifier'])->name('rapprochements.verifier');
    Route::post('/rapprochements/{rapprochement}/rejeter', [RapprochementController::class, 'rejeter'])->name('rapprochements.rejeter');
    Route::post('/rapprochements/{rapprochement}/finaliser', [RapprochementController::class, 'finaliser'])->name('rapprochements.finaliser');
    Route::get('/publiees', [PublicationReminderController::class, 'index'])->name('published');
    Route::post('/{declaration}/rappels', [PublicationReminderController::class, 'store'])->name('reminders.store');
    Route::post('/rappels/{reminder}/relancer', [PublicationReminderController::class, 'retry'])->name('reminders.retry');
    Route::delete('/commentaires/{commentaire}', [FeedController::class, 'supprimerCommentaire'])->name('commentaires.supprimer');
    Route::get('/', [ModerateurController::class, 'index'])->name('index');
    Route::get('/declarations/{declaration}', [DeclarationController::class, 'show'])
        ->name('declarations.show');
    Route::post('/{declaration}/valider', [ModerateurController::class, 'valider'])->name('valider');
    Route::post('/{declaration}/rejeter', [ModerateurController::class, 'rejeter'])->name('rejeter');
    Route::post('/utilisateurs/{user}/bloquer', [ModerateurController::class, 'bloquerUtilisateur'])->name('bloquer');
});

// ---------- Administrateur ----------
Route::middleware(['auth', 'verified', 'not_blocked', 'role:administrateur'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/utilisateurs', [UserController::class, 'index'])->name('users.index');
    Route::get('/utilisateurs/creer', [UserController::class, 'create'])->name('users.create');
    Route::post('/utilisateurs', [UserController::class, 'store'])->name('users.store');
    Route::post('/utilisateurs/{user}/envoyer-lien', [UserController::class, 'sendResetLink'])->name('users.send-reset');
    Route::put('/utilisateurs/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/utilisateurs/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/statistiques', [StatistiqueController::class, 'index'])->name('statistiques.index');
    Route::post('/statistiques', [StatistiqueController::class, 'generer'])->name('statistiques.generer');

    Route::get('/declarations/{declaration}', [DeclarationController::class, 'show'])
        ->name('declarations.show');
});
