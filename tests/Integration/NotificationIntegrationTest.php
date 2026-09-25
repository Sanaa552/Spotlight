<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Declaration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_notification_est_correctement_enregistree_pour_un_utilisateur(): void
    {
        $utilisateur = User::factory()->create();

        $declaration = Declaration::create([
            'user_id' => $utilisateur->id,
            'type' => 'perte',
            'categorie' => 'objet',
            'description' => 'Téléphone perdu',
            'lieu' => 'Douala',
            'statut' => 'en_attente',
        ]);

        $notification = AppNotification::create([
            'user_id' => $utilisateur->id,
            'declaration_id' => $declaration->id,
            'message' => 'Votre déclaration a été enregistrée.',
            'date_envoi' => now(),
            'canal' => 'app',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'id' => $notification->id,
            'user_id' => $utilisateur->id,
            'declaration_id' => $declaration->id,
            'message' => 'Votre déclaration a été enregistrée.',
            'canal' => 'app',
        ]);
    }
}