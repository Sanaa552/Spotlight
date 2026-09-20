<?php

namespace Tests\Feature;

use App\Jobs\PublishDeclaration;
use App\Models\Declaration;
use App\Models\PieceJointe;
use App\Models\User;
use App\Notifications\ModerationConfirmed;
use App\Notifications\NewPublicDeclaration;
use App\Services\MetaPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use LogicException;
use Tests\TestCase;

class ModerationPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_receives_details_only_after_both_publications_succeed(): void
    {
        Notification::fake();
        Storage::fake('public');
        $citizen = User::factory()->create();
        $subscriber = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $admin = User::factory()->create(['role' => 'administrateur']);
        $declaration = $this->declaration($citizen, 'photos-publiques/photo.jpg');
        Storage::disk('public')->put($declaration->photo_path, 'public-photo');

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldReceive('publishToFacebook')->once()
            ->andReturn(['success' => true, 'response' => ['id' => 'fb-admin']]);
        $meta->shouldReceive('facebookPhotoUrl')->once()
            ->andReturn(['success' => true, 'url' => 'https://scontent.example.test/photo.jpg']);
        $meta->shouldReceive('publishToInstagram')->once()
            ->andReturn(['success' => true, 'response' => ['id' => 'ig-admin']]);
        $meta->shouldReceive('publicPostUrl')->twice()
            ->andReturnUsing(fn ($channel) => "https://example.test/{$channel}-post");
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');
        Notification::assertNotSentTo($admin, ModerationConfirmed::class);
        Notification::assertNotSentTo($subscriber, NewPublicDeclaration::class);

        $this->runPublication($declaration, $moderator);

        Notification::assertSentToTimes($subscriber, NewPublicDeclaration::class, 1);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $subscriber->id,
            'declaration_id' => $declaration->id,
        ]);

        Notification::assertSentTo($admin, ModerationConfirmed::class, function ($notification) use ($declaration, $citizen, $moderator) {
            return $notification->declarationId === $declaration->id
                && $notification->citizenEmail === $citizen->email
                && $notification->moderatorEmail === $moderator->email
                && $notification->facebookUrl === 'https://example.test/facebook-post'
                && $notification->instagramUrl === 'https://example.test/instagram-post'
                && ! $notification->isPrivate;
        });
    }

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
        $meta->shouldReceive('facebookPhotoUrl')->once()->with('fb-123')
            ->andReturn(['success' => true, 'url' => 'https://scontent.example.test/portrait.jpg']);
        $meta->shouldReceive('publishToInstagram')->once()
            ->withArgs(fn ($imageUrl, $message) => $imageUrl === 'https://scontent.example.test/portrait.jpg'
                && str_contains($message, 'Portrait recherché'))
            ->andReturn(['success' => true, 'response' => ['id' => 'ig-123']]);
        $meta->shouldReceive('publicPostUrl')->twice()
            ->andReturnUsing(fn ($channel) => "https://example.test/{$channel}-post");
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)
            ->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'en_attente',
            'publication_status' => 'queued',
        ]);
        $this->assertDatabaseCount('jobs', 1);
        $this->runPublication($declaration, $moderator);

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'validee',
            'publication_status' => 'succeeded',
            'facebook_post_id' => 'fb-123',
            'instagram_post_id' => 'ig-123',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $citizen->id,
            'declaration_id' => $declaration->id,
        ]);
    }

    public function test_pending_private_photo_is_promoted_only_after_moderation_checks(): void
    {
        Storage::fake('public');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, 'photos-en-attente/photo.jpg');
        Storage::disk('local')->put($declaration->photo_path, 'photo-content');
        Storage::disk('public')->assertMissing('photos-publiques/photo.jpg');

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldReceive('publishToFacebook')->once()
            ->withArgs(fn ($message, $imageUrl) => str_contains($imageUrl, 'photos-publiques/photo.jpg')
                && Storage::disk('public')->exists('photos-publiques/photo.jpg'))
            ->andReturn(['success' => true, 'response' => ['id' => 'fb-promoted']]);
        $meta->shouldReceive('facebookPhotoUrl')->once()->with('fb-promoted')
            ->andReturn(['success' => true, 'url' => 'https://scontent.example.test/photo.jpg']);
        $meta->shouldReceive('publishToInstagram')->once()
            ->andReturn(['success' => true, 'response' => ['id' => 'ig-promoted']]);
        $meta->shouldReceive('publicPostUrl')->twice()
            ->andReturnUsing(fn ($channel) => "https://example.test/{$channel}-post");
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');

        $this->runPublication($declaration, $moderator);
        Storage::disk('local')->assertMissing('photos-en-attente/photo.jpg');
        Storage::disk('public')->assertExists('photos-publiques/photo.jpg');
        $this->assertSame('photos-publiques/photo.jpg', $declaration->fresh()->photo_path);
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
        $meta->shouldReceive('facebookPhotoUrl')->twice()->with('fb-456')
            ->andReturn(['success' => true, 'url' => 'https://scontent.example.test/photo.jpg']);
        $meta->shouldReceive('publishToInstagram')->twice()
            ->andReturn(
                ['success' => false, 'error' => 'Meta indisponible'],
                ['success' => true, 'response' => ['id' => 'ig-456']]
            );
        $meta->shouldReceive('publicPostUrl')->twice()
            ->andReturnUsing(fn ($channel) => "https://example.test/{$channel}-post");
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)
            ->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');
        $this->runPublication($declaration, $moderator);

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'en_attente',
            'publication_status' => 'failed',
            'facebook_post_id' => 'fb-456',
            'instagram_post_id' => null,
        ]);
        $this->assertDatabaseCount('app_notifications', 0);

        $this->actingAs($moderator)
            ->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');
        $this->runPublication($declaration, $moderator);

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'validee',
            'facebook_post_id' => 'fb-456',
            'instagram_post_id' => 'ig-456',
        ]);
    }

    public function test_unavailable_facebook_photo_keeps_declaration_pending_without_instagram_post(): void
    {
        Storage::fake('public');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, 'photos-publiques/photo.jpg');
        Storage::disk('public')->put($declaration->photo_path, 'public-photo');
        $declaration->update(['facebook_post_id' => 'fb-existing']);

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldNotReceive('publishToFacebook');
        $meta->shouldReceive('facebookPhotoUrl')->once()->with('fb-existing')
            ->andReturn(['success' => false, 'channel' => 'facebook', 'status' => 503]);
        $meta->shouldNotReceive('publishToInstagram');
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');
        $this->runPublication($declaration, $moderator);

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'en_attente',
            'publication_status' => 'failed',
            'facebook_post_id' => 'fb-existing',
            'instagram_post_id' => null,
        ]);
    }

    public function test_expired_meta_token_has_actionable_moderation_message(): void
    {
        Storage::fake('public');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, 'photos-publiques/photo.jpg');
        Storage::disk('public')->put($declaration->photo_path, 'public-photo');
        $declaration->update(['facebook_post_id' => 'fb-existing']);

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldNotReceive('publishToFacebook');
        $meta->shouldReceive('facebookPhotoUrl')->once()->with('fb-existing')
            ->andReturn([
                'success' => false, 'channel' => 'facebook', 'status' => 401,
                'response' => ['error' => ['code' => 190]],
            ]);
        $meta->shouldNotReceive('publishToInstagram');
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');
        $this->runPublication($declaration, $moderator);

        $this->assertSame(
            'Connexion Meta expirée. Demandez à l’administrateur de renouveler le jeton d’accès.',
            $declaration->fresh()->publication_error
        );
    }

    public function test_facebook_photo_source_uses_existing_photo_id(): void
    {
        config()->set('services.meta.page_access_token', 'test-token');
        config()->set('services.meta.graph_version', 'v26.0');
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'images' => [['source' => 'https://scontent.example.test/photo.jpg']],
            ]),
        ]);

        $result = (new MetaPublishingService)->facebookPhotoUrl('fb-existing');

        $this->assertTrue($result['success']);
        $this->assertSame('https://scontent.example.test/photo.jpg', $result['url']);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_ends_with($request->url(), '/fb-existing?fields=images')
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_public_social_links_are_read_from_meta_without_republishing(): void
    {
        config()->set('services.meta.page_access_token', 'test-token');
        config()->set('services.meta.graph_version', 'v26.0');
        Http::fake(function ($request) {
            return str_contains($request->url(), '/fb-photo')
                ? Http::response(['link' => 'https://www.facebook.com/photo.php?fbid=123'])
                : Http::response(['permalink' => 'https://www.instagram.com/p/test/']);
        });

        $meta = new MetaPublishingService;
        $this->assertSame('https://www.facebook.com/photo.php?fbid=123', $meta->publicPostUrl('facebook', 'fb-photo'));
        $this->assertSame('https://www.instagram.com/p/test/', $meta->publicPostUrl('instagram', 'ig-post'));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), 'fields=permalink'));
    }

    public function test_instagram_waits_for_ready_container_before_publishing(): void
    {
        config()->set('services.meta.page_access_token', 'test-token');
        config()->set('services.meta.instagram_id', 'ig-account');
        config()->set('services.meta.graph_version', 'v26.0');
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/media_publish')) {
                return Http::response(['id' => 'ig-post']);
            }
            if (str_contains($request->url(), '/media')) {
                return Http::response(['id' => 'ig-container']);
            }

            return Http::response(['status_code' => 'FINISHED']);
        });

        $result = (new MetaPublishingService)->publishToInstagram(
            'https://scontent.example.test/photo.jpg',
            'Annonce Spotlight'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('ig-post', $result['response']['id']);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/ig-container?fields=status_code%2Cstatus'));
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

    public function test_moderator_cannot_publish_without_private_police_document(): void
    {
        Storage::fake('public');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, 'photos-publiques/photo.jpg');
        Storage::disk('public')->put($declaration->photo_path, 'photo');
        $declaration->piecesJointes()->delete();

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldNotReceive('publishToFacebook');
        $meta->shouldNotReceive('publishToInstagram');
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('warning');
        $this->assertSame('en_attente', $declaration->fresh()->statut);
    }

    public function test_found_person_is_confirmed_privately_without_meta_publication(): void
    {
        Notification::fake();
        Storage::fake('local');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $admin = User::factory()->create(['role' => 'administrateur']);
        $declaration = $citizen->declarations()->create([
            'type' => 'decouverte', 'categorie' => 'personne',
            'description' => 'Dossier personnel confidentiel',
            'statut' => 'en_attente',
        ]);
        Storage::disk('local')->put('declarations-privees/signalement.pdf', 'document');
        $declaration->piecesJointes()->create([
            'type_document' => 'preuve_signalement', 'disque' => 'local',
            'chemin' => 'declarations-privees/signalement.pdf',
            'nom_original' => 'signalement.pdf',
        ]);
        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldNotReceive('publishToFacebook');
        $meta->shouldNotReceive('publishToInstagram');
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id, 'statut' => 'validee',
            'facebook_post_id' => null, 'instagram_post_id' => null,
        ]);
        Notification::assertSentTo($admin, ModerationConfirmed::class, fn ($notification) => $notification->isPrivate
            && $notification->facebookUrl === null);
        $this->get(route('dashboard'))->assertDontSee('Dossier personnel confidentiel');
        $this->get(route('public.declarations.index', ['onglet' => 'decouvertes']))
            ->assertDontSee('Dossier personnel confidentiel');
    }

    public function test_found_object_waits_for_receipt_before_meta_publication(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $citizen->declarations()->create([
            'type' => 'decouverte', 'categorie' => 'objet',
            'description' => 'Objet trouvé sous contrôle.',
            'photo_path' => 'photos-publiques/objet.jpg',
            'statut' => 'en_attente',
        ]);
        Storage::disk('public')->put($declaration->photo_path, 'photo');
        Storage::disk('local')->put('declarations-privees/lieu.mp4', 'video');
        $declaration->piecesJointes()->create([
            'type_document' => 'preuve_decouverte', 'disque' => 'local',
            'chemin' => 'declarations-privees/lieu.mp4', 'nom_original' => 'lieu.mp4',
        ]);

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldReceive('publishToFacebook')->once()
            ->andReturn(['success' => true, 'response' => ['id' => 'fb-object']]);
        $meta->shouldReceive('facebookPhotoUrl')->once()->with('fb-object')
            ->andReturn(['success' => true, 'url' => 'https://scontent.example.test/objet.jpg']);
        $meta->shouldReceive('publishToInstagram')->once()
            ->andReturn(['success' => true, 'response' => ['id' => 'ig-object']]);
        $meta->shouldReceive('publicPostUrl')->twice()
            ->andReturnUsing(fn ($channel) => "https://example.test/{$channel}-post");
        $this->app->instance(MetaPublishingService::class, $meta);

        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('warning');
        $this->assertSame('en_attente', $declaration->fresh()->statut);

        Storage::disk('local')->put('declarations-privees/recepisse.pdf', 'receipt');
        $declaration->piecesJointes()->create([
            'type_document' => 'preuve_signalement', 'disque' => 'local',
            'chemin' => 'declarations-privees/recepisse.pdf', 'nom_original' => 'recepisse.pdf',
        ]);

        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');
        $this->runPublication($declaration, $moderator);
        $this->assertSame('validee', $declaration->fresh()->statut);
        $this->get(route('dashboard'))->assertSee('Objet trouvé sous contrôle.');
    }

    public function test_legacy_validated_case_without_meta_ids_is_not_public(): void
    {
        $citizen = User::factory()->create();
        $declaration = $citizen->declarations()->create([
            'type' => 'perte', 'categorie' => 'objet',
            'description' => 'Ancien dossier sans publication Meta',
            'statut' => 'validee',
        ]);

        $this->actingAs($citizen)->get(route('dashboard'))
            ->assertDontSee('Ancien dossier sans publication Meta');
        $this->get(route('public.declarations.index'))
            ->assertDontSee('Ancien dossier sans publication Meta');
    }

    public function test_legacy_case_with_meta_ids_but_no_private_proof_is_not_public_or_commentable(): void
    {
        $citizen = User::factory()->create();
        $declaration = $citizen->declarations()->create([
            'type' => 'perte', 'categorie' => 'objet',
            'description' => 'Ancien dossier sans justificatif',
            'photo_path' => 'photos-publiques/ancienne.jpg',
            'statut' => 'validee',
            'facebook_post_id' => 'fb-old',
            'instagram_post_id' => 'ig-old',
        ]);

        $this->actingAs($citizen)->get(route('dashboard'))
            ->assertDontSee('Ancien dossier sans justificatif');
        $this->get(route('public.declarations.index'))
            ->assertDontSee('Ancien dossier sans justificatif');
        $this->post(route('declarations.commenter', $declaration), ['contenu' => 'Test'])
            ->assertNotFound();
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
            ->assertSessionHas('success');
        $this->runPublication($declaration, $moderator);

        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'en_attente',
            'publication_status' => 'failed',
            'facebook_post_id' => null,
            'instagram_post_id' => null,
        ]);
    }

    public function test_pending_publication_cannot_be_queued_twice_or_rejected(): void
    {
        Storage::fake('public');
        $citizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, 'photos-publiques/photo.jpg');
        Storage::disk('public')->put($declaration->photo_path, 'public-photo');

        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('success');
        $this->actingAs($moderator)->post(route('moderation.valider', $declaration))
            ->assertSessionHas('warning');
        $this->actingAs($moderator)->post(route('moderation.rejeter', $declaration), [
            'motif_rejet' => 'Refus pendant la publication',
        ])->assertSessionHas('warning');

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('declarations', [
            'id' => $declaration->id,
            'statut' => 'en_attente',
            'publication_status' => 'queued',
        ]);
    }

    public function test_publication_status_is_private_to_owner_and_moderation(): void
    {
        $citizen = User::factory()->create();
        $other = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->declaration($citizen, null);
        $declaration->update([
            'publication_status' => 'failed',
            'publication_error' => 'Facebook : code Meta 190.',
        ]);

        $this->actingAs($other)->getJson(route('declarations.publication-status', $declaration))
            ->assertForbidden();
        $this->actingAs($citizen)->getJson(route('declarations.publication-status', $declaration))
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', null);
        $this->actingAs($moderator)->getJson(route('declarations.publication-status', $declaration))
            ->assertOk()
            ->assertJsonPath('error', 'Facebook : code Meta 190.');
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

    private function runPublication(Declaration $declaration, User $moderator): void
    {
        (new PublishDeclaration($declaration->id, $moderator->id))
            ->handle($this->app->make(MetaPublishingService::class));
    }

    private function declaration(User $citizen, ?string $photoPath): Declaration
    {
        Storage::fake('local');
        $declaration = $citizen->declarations()->create([
            'type' => 'perte',
            'categorie' => 'personne',
            'type_perte' => 'Portrait recherché',
            'description' => 'Signalement public.',
            'photo_path' => $photoPath,
            'statut' => 'en_attente',
        ]);

        Storage::disk('local')->put('declarations-privees/signalement.pdf', 'police-report');
        PieceJointe::create([
            'declaration_id' => $declaration->id,
            'type_document' => 'declaration_perte',
            'disque' => 'local',
            'chemin' => 'declarations-privees/signalement.pdf',
            'nom_original' => 'signalement.pdf',
            'type_mime' => 'application/pdf',
        ]);

        return $declaration;
    }
}
