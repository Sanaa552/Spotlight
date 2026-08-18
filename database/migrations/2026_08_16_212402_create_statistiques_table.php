<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistiques', function (Blueprint $table) {
            $table->id();

            // Administrateur générant la statistique
            $table->foreignId('administrateur_id')->constrained('users')->cascadeOnDelete();

            $table->string('type'); // ex: declarations_par_mois, taux_restitution...
            $table->timestamp('date_generation')->useCurrent();
            $table->json('donnees')->nullable(); // résultat de calcul / réponse API statistique

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistiques');
    }
};