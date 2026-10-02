<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->string('poste_verifie_nom')->nullable();
            $table->string('poste_verification_methode', 30)->nullable();
            $table->text('poste_verification_note')->nullable();
            $table->timestamp('poste_verifie_at')->nullable();
            $table->foreignId('poste_verifie_par')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('rapprochements', function (Blueprint $table) {
            $table->text('restitution_note')->nullable();
        });

        Schema::table('publication_reminders', function (Blueprint $table) {
            $table->string('kind', 20)->default('rappel')->index();
        });
    }

    public function down(): void
    {
        Schema::table('publication_reminders', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
        Schema::table('rapprochements', fn (Blueprint $table) => $table->dropColumn('restitution_note'));
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('poste_verifie_par');
            $table->dropColumn(['poste_verifie_nom', 'poste_verification_methode', 'poste_verification_note', 'poste_verifie_at']);
        });
    }
};
