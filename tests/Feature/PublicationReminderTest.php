<?php

namespace Tests\Feature;

use App\Jobs\PublishReminder;
use App\Models\Declaration;
use App\Models\PublicationReminder;
use App\Models\User;
use App\Services\MetaPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PublicationReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_moderation_can_explicitly_queue_selected_reminders_without_changing_original_posts(): void
    {
        Queue::fake();
        Storage::fake('public');
        $owner = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->publishedDeclaration($owner);

        $this->actingAs($owner)->post(route('moderation.reminders.store', $declaration), [
            'selection' => 'facebook',
        ])->assertForbidden();
        $this->assertDatabaseCount('publication_reminders', 0);

        $this->actingAs($moderator)->get(route('moderation.published'))
            ->assertOk()->assertSee('Publier un rappel');
        $this->actingAs($moderator)->post(route('moderation.reminders.store', $declaration), [
            'selection' => 'both',
        ])->assertSessionHas('success');
        $this->assertDatabaseCount('publication_reminders', 2);
        Queue::assertPushed(PublishReminder::class, 2);

        $this->actingAs($moderator)->post(route('moderation.reminders.store', $declaration), [
            'selection' => 'facebook',
        ])->assertSessionHas('warning');
        $this->assertDatabaseCount('publication_reminders', 2);
        $this->assertSame('fb-original', $declaration->fresh()->facebook_post_id);
        $this->assertSame('ig-original', $declaration->fresh()->instagram_post_id);
        $this->assertSame('validee', $declaration->fresh()->statut);
    }

    public function test_instagram_only_reminder_uses_existing_public_facebook_photo_and_does_not_repost_facebook(): void
    {
        Storage::fake('public');
        $declaration = $this->publishedDeclaration(User::factory()->create());
        $reminder = PublicationReminder::create([
            'declaration_id' => $declaration->id,
            'user_id' => User::factory()->create(['role' => 'moderateur'])->id,
            'channel' => 'instagram',
            'status' => 'queued',
        ]);
        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldNotReceive('publishToFacebook');
        $meta->shouldReceive('facebookPhotoUrl')->once()->with('fb-original')
            ->andReturn(['success' => true, 'url' => 'https://scontent.example.test/photo.jpg']);
        $meta->shouldReceive('publishToInstagram')->once()
            ->withArgs(fn ($url, $message) => $url === 'https://scontent.example.test/photo.jpg'
                && str_contains($message, 'RAPPEL'))
            ->andReturn(['success' => true, 'response' => ['id' => 'ig-reminder']]);
        $meta->shouldReceive('publicPostUrl')->once()->with('instagram', 'ig-reminder')
            ->andReturn('https://www.instagram.com/p/reminder/');

        (new PublishReminder($reminder->id))->handle($meta);
        $this->assertSame('succeeded', $reminder->fresh()->status);
        $this->assertSame('ig-reminder', $reminder->fresh()->post_id);
        $this->assertSame('ig-original', $declaration->fresh()->instagram_post_id);
    }

    public function test_instagram_only_selection_queues_no_facebook_reminder(): void
    {
        Queue::fake();
        Storage::fake('public');
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->publishedDeclaration(User::factory()->create());

        $this->actingAs($moderator)->get(route('moderation.published'))
            ->assertOk()
            ->assertSee('x-data="{}"', false)
            ->assertSee('Facebook seul')
            ->assertSee('Instagram seul')
            ->assertSee('Facebook et Instagram');
        $this->actingAs($moderator)->post(route('moderation.reminders.store', $declaration), [
            'selection' => 'instagram',
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('publication_reminders', 1);
        $this->assertDatabaseHas('publication_reminders', [
            'declaration_id' => $declaration->id,
            'channel' => 'instagram',
        ]);
        Queue::assertPushed(PublishReminder::class, 1);
    }

    public function test_no_reminder_is_queued_without_an_explicit_channel_choice(): void
    {
        Queue::fake();
        Storage::fake('public');
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->publishedDeclaration(User::factory()->create());

        $this->actingAs($moderator)->post(route('moderation.reminders.store', $declaration), [])
            ->assertSessionHasErrors('selection');
        $this->assertDatabaseCount('publication_reminders', 0);
        Queue::assertNothingPushed();
    }

    public function test_failed_reminder_can_be_retried_without_reposting_an_already_confirmed_post(): void
    {
        Queue::fake();
        Storage::fake('public');
        $declaration = $this->publishedDeclaration(User::factory()->create());
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $reminder = PublicationReminder::create([
            'declaration_id' => $declaration->id,
            'user_id' => $moderator->id,
            'channel' => 'facebook',
            'status' => 'failed',
            'post_id' => 'fb-already-published',
        ]);

        $this->actingAs($moderator)->post(route('moderation.reminders.retry', $reminder))
            ->assertSessionHas('success');
        Queue::assertPushed(PublishReminder::class, 1);

        $meta = Mockery::mock(MetaPublishingService::class);
        $meta->shouldNotReceive('publishToFacebook');
        $meta->shouldReceive('publicPostUrl')->once()->with('facebook', 'fb-already-published')
            ->andReturn('https://www.facebook.com/photo.php?fbid=123');
        (new PublishReminder($reminder->id))->handle($meta);

        $this->assertSame('succeeded', $reminder->fresh()->status);
        $this->assertSame('fb-already-published', $reminder->fresh()->post_id);
        $this->assertSame('fb-original', $declaration->fresh()->facebook_post_id);
    }

    public function test_failed_reminder_cannot_be_retried_while_same_channel_is_already_queued(): void
    {
        Queue::fake();
        Storage::fake('public');
        $declaration = $this->publishedDeclaration(User::factory()->create());
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $failed = PublicationReminder::create([
            'declaration_id' => $declaration->id,
            'user_id' => $moderator->id,
            'channel' => 'facebook',
            'status' => 'failed',
        ]);
        PublicationReminder::create([
            'declaration_id' => $declaration->id,
            'user_id' => $moderator->id,
            'channel' => 'facebook',
            'status' => 'queued',
        ]);

        $this->actingAs($moderator)->post(route('moderation.reminders.retry', $failed))
            ->assertSessionHas('warning');
        $this->assertSame('failed', $failed->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_private_found_person_cannot_be_reminded(): void
    {
        Queue::fake();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = User::factory()->create()->declarations()->create([
            'type' => 'decouverte',
            'categorie' => 'personne',
            'description' => 'Signalement privé',
            'statut' => 'validee',
        ]);

        $this->actingAs($moderator)->post(route('moderation.reminders.store', $declaration), [
            'selection' => 'facebook',
        ])->assertNotFound();
        $this->assertDatabaseCount('publication_reminders', 0);
    }

    private function publishedDeclaration(User $owner): Declaration
    {
        $declaration = $owner->declarations()->create([
            'type' => 'perte',
            'categorie' => 'objet',
            'type_perte' => 'Sac noir',
            'description' => 'Sac noir perdu.',
            'photo_path' => 'photos-publiques/sac.jpg',
            'statut' => 'validee',
            'facebook_post_id' => 'fb-original',
            'instagram_post_id' => 'ig-original',
            'publication_status' => 'succeeded',
        ]);
        $declaration->piecesJointes()->create([
            'type_document' => 'declaration_perte',
            'disque' => 'local',
            'chemin' => 'declarations-privees/preuve.pdf',
            'nom_original' => 'preuve.pdf',
        ]);
        Storage::disk('public')->put('photos-publiques/sac.jpg', 'public-photo');

        return $declaration;
    }
}
