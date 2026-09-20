<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapprochements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perte_id')->constrained('declarations')->cascadeOnDelete();
            $table->foreignId('decouverte_id')->unique()->constrained('declarations')->cascadeOnDelete();
            $table->foreignId('moderateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut')->default('propose');
            $table->timestamp('verifie_at')->nullable();
            $table->timestamp('proprietaire_confirme_at')->nullable();
            $table->timestamp('decouvreur_confirme_at')->nullable();
            $table->timestamp('restitue_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapprochements');
    }
};
