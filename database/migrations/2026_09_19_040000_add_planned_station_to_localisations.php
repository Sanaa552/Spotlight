<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('localisations', function (Blueprint $table) {
            $table->string('poste_prevu')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('localisations', function (Blueprint $table) {
            $table->dropColumn('poste_prevu');
        });
    }
};
