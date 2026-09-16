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
use Illuminate\Support\Facades\Route;

Route::get('/declarations-publiques', [PublicDeclarationController::class, 'index'])->name('public.declarations.index');
Route::view('/a-propos', 'public.about')->name('public.about');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [FeedController::class, 'index'])->middleware(['auth', 'verified', 'not_blocked'])->name('dashboard');

Route::post('/declarations/{declaration}/commenter', [FeedController::class, 'commenter'])
    ->middleware(['auth', 'verified', 'not_blocked'])
    ->name('declarations.commenter');

Route::get('/pieces-jointes/{pieceJointe}/telecharger', [DeclarationController::class, 'telechargerPieceJointe'])
    ->middleware(['auth', 'verified', 'not_blocked'])
    ->name('pieces-jointes.telecharger');

Route::middleware(['auth', 'not_blocked'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// ---------- Citoyen ----------
Route::middleware(['auth', 'verified', 'not_blocked', 'role:citoyen'])->group(function () {
    Route::get('/declarations', [DeclarationController::class, 'index'])->name('declarations.index');
    Route::get('/declarations/creer', [DeclarationController::class, 'create'])->name('declarations.create');
    Route::post('/declarations', [DeclarationController::class, 'store'])->name('declarations.store');
    Route::get('/declarations/{declaration}', [DeclarationController::class, 'show'])->name('declarations.show');
    Route::post('/declarations/{declaration}/confirmer-restitution', [DeclarationController::class, 'confirmerRestitution'])
        ->name('declarations.confirmer-restitution');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{appNotification}/lue', [NotificationController::class, 'marquerLue'])
        ->name('notifications.marquer-lue');
    Route::get('/declarations/{declaration}/commissariats', [CommissariatController::class, 'proches'])
        ->name('declarations.commissariats');
});

// ---------- Modérateur ----------
Route::middleware(['auth', 'verified', 'not_blocked', 'role:moderateur,administrateur'])->prefix('moderation')->name('moderation.')->group(function () {
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
