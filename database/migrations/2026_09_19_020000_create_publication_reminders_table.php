<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declaration_id')->constrained('declarations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20)->default('queued');
            $table->string('post_id')->nullable();
            $table->text('post_url')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['declaration_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_reminders');
    }
};
