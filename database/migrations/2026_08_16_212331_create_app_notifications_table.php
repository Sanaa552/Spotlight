<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Table métier distincte de la table "notifications" native de Laravel
    // (celle-ci correspond à la classe UML "Notification")
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();

            // Citoyen destinataire
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Déclaration à l'origine de la notification (optionnelle)
            $table->foreignId('declaration_id')->nullable()
                  ->constrained('declarations')->cascadeOnDelete();

            $table->text('message');
            $table->timestamp('date_envoi')->useCurrent();
            $table->boolean('lu')->default(false);

            // Statut d'envoi SMS via API Twilio
            $table->string('twilio_sid')->nullable();
            $table->enum('canal', ['sms', 'app'])->default('app');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};