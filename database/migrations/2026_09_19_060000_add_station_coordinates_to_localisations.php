<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('localisations', function (Blueprint $table) {
            $table->decimal('poste_latitude', 10, 7)->nullable();
            $table->decimal('poste_longitude', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('localisations', function (Blueprint $table) {
            $table->dropColumn(['poste_latitude', 'poste_longitude']);
        });
    }
};
