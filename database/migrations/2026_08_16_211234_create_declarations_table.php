<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declarations', function (Blueprint $table) {
            $table->id();

            // Citoyen auteur de la déclaration
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Modérateur ayant traité la déclaration (nullable tant que non traitée)
            $table->foreignId('moderateur_id')->nullable()
                  ->constrained('users')->nullOnDelete();

            // Discriminant d'héritage Declaration -> DeclarationPerte / DeclarationDecouverte
            $table->enum('type', ['perte', 'decouverte']);

            $table->string('categorie'); // ex: personne, objet (cas d'utilisation "Personne"/"Objet")
            $table->text('description');
            $table->string('lieu')->nullable(); // résumé textuel du lieu

            $table->enum('statut', ['en_attente', 'validee', 'rejetee', 'cloturee'])
                  ->default('en_attente');

            // Attribut spécifique DeclarationPerte
            $table->string('type_perte')->nullable();

            // Attribut spécifique DeclarationDecouverte
            $table->string('type_decouverte')->nullable();

            // Pièce jointe : "Joindre photo ou pièce justificative"
            $table->string('photo_path')->nullable();

            $table->text('motif_rejet')->nullable();
            $table->timestamp('cloturee_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declarations');
    }
};