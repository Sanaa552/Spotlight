<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces_jointes', function (Blueprint $table) {
            $table->string('type_document', 40)->default('piece_jointe')->after('declaration_id');
            $table->string('disque', 30)->default('public')->after('type_document');
            $table->index(['declaration_id', 'type_document']);
        });
    }

    public function down(): void
    {
        Schema::table('pieces_jointes', function (Blueprint $table) {
            $table->dropIndex(['declaration_id', 'type_document']);
            $table->dropColumn(['type_document', 'disque']);
        });
    }
};
