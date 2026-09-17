<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\PieceJointe;
use App\Models\User;
use App\Services\MetaPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use LogicException;
use Tests\TestCase;

class ModerationPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_explicit_public_photo_is_sent_to_meta_and_validation_waits_for_both_posts(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, 'photos-publiques/portrait.jpg');
        Storage::disk('public')->put($declaration->photo_path, 'public-photo');
        Storage::disk('local')->put('declarations-privees/cni.jpg', 'private-id-card');

        PieceJointe::create([
            'declaration_id' => $declaration->id,
            'type_document' => 'piece_jointe',
            'disque' => 'local',
            'chemin' => 'declarations-privees/cni.jpg',
            'nom_original' => 'cni.jpg',
            'type_mime' => 'image/jpeg',
        ]);

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldReceive('publishToFacebook')->once()
            ->withArgs(fn ($message, $imageUrl) => str_contains($message, 'Portrait recherché')
                && str_contains($imageUrl, 'photos-publiques/portrait.jpg')
                && ! str_contains($imageUrl, 'cni.jpg'))
            ->andReturn(['success' => true, 'response' => ['id' => 'fb-123']]);
        $meta->shouldReceive('publishToInstagram')->once()
            ->withArgs(fn ($imageUrl, $message) => str_contains($imageUrl, 'photos-publiques/portrait.jpg')
                && str_contains($message, 'Portrait recherché'))
            ->andReturn(['success' => true, 'response' => ['id' => 'ig-123']]);
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)
            ->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'validee',
            'facebook_post_id' => 'fb-123',
            'instagram_post_id' => 'ig-123',
        ]);
    }

    public function test_instagram_failure_keeps_pending_and_retry_does_not_repost_facebook(): void
    {
        Storage::fake('public');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, 'photos-publiques/photo.jpg');
        Storage::disk('public')->put($declaration->photo_path, 'public-photo');

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldReceive('publishToFacebook')->once()
            ->andReturn(['success' => true, 'response' => ['id' => 'fb-456']]);
        $meta->shouldReceive('publishToInstagram')->twice()
            ->andReturn(
                ['success' => false, 'error' => 'Meta indisponible'],
                ['success' => true, 'response' => ['id' => 'ig-456']]
            );
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)
            ->post(route('moderation.valider', $declaration))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'en_attente',
            'facebook_post_id' => 'fb-456',
            'instagram_post_id' => null,
        ]);

        $this->actingAs($moderator)
            ->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'validee',
            'facebook_post_id' => 'fb-456',
            'instagram_post_id' => 'ig-456',
        ]);
    }

    public function test_private_image_cannot_be_used_when_public_photo_is_missing(): void
    {
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, null);

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldNotReceive('publishToFacebook');
        $meta->shouldNotReceive('publishToInstagram');
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)
            ->post(route('moderation.valider', $declaration))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('declarations', ['id' => $declaration->id, 'statut' => 'en_attente']);
    }

    public function test_facebook_failure_keeps_declaration_pending_and_skips_instagram(): void
    {
        Storage::fake('public');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, 'photos-publiques/photo.jpg');
        Storage::disk('public')->put($declaration->photo_path, 'public-photo');

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldReceive('publishToFacebook')->once()
            ->andReturn(['success' => false, 'error' => 'Meta indisponible']);
        $meta->shouldNotReceive('publishToInstagram');
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)
            ->post(route('moderation.valider', $declaration))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'en_attente',
            'facebook_post_id' => null,
            'instagram_post_id' => null,
        ]);
    }

    public function test_model_cannot_validate_or_close_before_publication_and_restitution_requires_owner(): void
    {
        $citizen = User::factory()->create();
        $otherCitizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, 'photos-publiques/photo.jpg');

        try {
            $declaration->publier();
            $this->fail('La validation sans publication devait être refusée.');
        } catch (LogicException) {
            $this->assertSame('en_attente', $declaration->fresh()->statut);
        }

        $this->actingAs($citizen)
            ->post(route('declarations.confirmer-restitution', $declaration))
            ->assertForbidden();

        $declaration->update(['facebook_post_id' => 'fb-1', 'instagram_post_id' => 'ig-1']);
        $declaration->publier();

        $this->actingAs($otherCitizen)
            ->post(route('declarations.confirmer-restitution', $declaration))
            ->assertForbidden();
        $this->actingAs($moderator)
            ->post(route('declarations.confirmer-restitution', $declaration))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->post(route('declarations.confirmer-restitution', $declaration))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('declarations', ['id' => $declaration->id, 'statut' => 'cloturee']);
    }

    private function declaration(User $citizen, ?string $photoPath): Declaration
    {
        return $citizen->declarations()->create([
            'type' => 'perte',
            'categorie' => 'personne',
            'type_perte' => 'Portrait recherché',
            'description' => 'Signalement public.',
            'photo_path' => $photoPath,
            'statut' => 'en_attente',
        ]);
    }
}
