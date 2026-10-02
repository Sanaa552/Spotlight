<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\PieceJointe;
use App\Models\User;
use App\Notifications\DiscoveryProofRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DiscoveryProofRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_can_request_missing_proof_once_and_citizen_receives_private_notifications(): void
    {
        Notification::fake();
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->discovery($citizen);

        $this->actingAs($moderator)
            ->get(route('moderation.declarations.show', $declaration))
            ->assertOk()
            ->assertSee('Demander le justificatif')
            ->assertSee('x-on:submit="sending = true"', false)
            ->assertDontSee('x-on:click="sending = true"', false);

        $this->post(route('moderation.demander-justificatif', $declaration))
            ->assertSessionHas('success');

        Notification::assertSentToTimes($citizen, DiscoveryProofRequested::class, 1);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $citizen->id,
            'declaration_id' => $declaration->id,
            'canal' => 'app',
        ]);
        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'en_attente',
            'publication_status' => null,
        ]);

        $this->post(route('moderation.demander-justificatif', $declaration))
            ->assertSessionHas('warning');
        Notification::assertSentToTimes($citizen, DiscoveryProofRequested::class, 1);
        $this->assertDatabaseCount('app_notifications', 1);
    }

    public function test_request_is_unavailable_when_proof_exists_or_discovery_is_no_longer_pending(): void
    {
        Notification::fake();
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->discovery($citizen);

        PieceJointe::create([
            'declaration_id' => $declaration->id,
            'type_document' => 'preuve_signalement',
            'disque' => 'local',
            'chemin' => 'declarations-privees/recepisse.pdf',
            'nom_original' => 'recepisse.pdf',
        ]);

        $this->actingAs($moderator)
            ->post(route('moderation.demander-justificatif', $declaration))
            ->assertSessionHas('warning');
        $this->get(route('moderation.declarations.show', $declaration))
            ->assertDontSee('Demander le justificatif');

        $declaration->piecesJointes()->delete();
        $declaration->update(['statut' => 'rejetee']);
        $this->post(route('moderation.demander-justificatif', $declaration))
            ->assertSessionHas('warning');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_citizen_cannot_request_proof_from_another_account(): void
    {
        Notification::fake();
        $citizen = User::factory()->create();
        $declaration = $this->discovery($citizen);

        $this->actingAs($citizen)
            ->post(route('moderation.demander-justificatif', $declaration))
            ->assertForbidden();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('app_notifications', 0);
    }

    private function discovery(User $citizen): Declaration
    {
        return $citizen->declarations()->create([
            'type' => 'decouverte',
            'categorie' => 'objet',
            'type_decouverte' => 'Portefeuille trouvé',
            'description' => 'Portefeuille remis prochainement aux autorités.',
            'statut' => 'en_attente',
        ]);
    }
}
