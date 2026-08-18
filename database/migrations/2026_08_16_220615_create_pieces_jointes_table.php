<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('declaration_id')->constrained('declarations')->cascadeOnDelete();

            $table->string('chemin');           // ex: declarations/xxxx.jpg
            $table->string('nom_original');     // nom du fichier envoyé par l'utilisateur
            $table->string('type_mime')->nullable();
            $table->unsignedInteger('taille')->nullable(); // en octets

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces_jointes');
    }
};