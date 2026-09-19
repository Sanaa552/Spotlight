<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\User;
use App\Notifications\CommentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FeedCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_receives_app_and_email_notification_for_another_users_comment(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $declaration = $this->publishedDeclaration($owner);

        $this->actingAs($commenter)->post(route('declarations.commenter', $declaration), [
            'contenu' => 'Je l’ai vu près du marché.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'declaration_id' => $declaration->id,
        ]);
        Notification::assertSentTo($owner, CommentReceived::class, fn ($notification) => $notification->declarationId === $declaration->id
            && $notification->authorName === $commenter->name);
    }

    public function test_owners_comment_notifies_moderation_but_not_the_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $admin = User::factory()->create(['role' => 'administrateur']);
        $declaration = $this->publishedDeclaration($owner);

        $this->actingAs($owner)->post(route('declarations.commenter', $declaration), [
            'contenu' => 'Information complémentaire.',
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('app_notifications', 2);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $moderator->id]);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $admin->id]);
        Notification::assertSentTo($moderator, CommentReceived::class);
        Notification::assertSentTo($admin, CommentReceived::class);
        Notification::assertNotSentTo($owner, CommentReceived::class);
    }

    public function test_outside_citizen_alerts_owner_and_moderation_and_must_be_logged_in(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $admin = User::factory()->create(['role' => 'administrateur']);
        $declaration = $this->publishedDeclaration($owner);

        $this->post(route('declarations.commenter', $declaration), ['contenu' => 'Je peux aider.'])
            ->assertRedirect(route('login'));
        $this->assertDatabaseCount('commentaires', 0);

        $this->actingAs($outsider)->post(route('declarations.commenter', $declaration), [
            'contenu' => 'Je peux aider.',
        ])->assertSessionHas('success');

        foreach ([$owner, $moderator, $admin] as $recipient) {
            $this->assertDatabaseHas('app_notifications', [
                'user_id' => $recipient->id,
                'declaration_id' => $declaration->id,
            ]);
            Notification::assertSentTo($recipient, CommentReceived::class);
        }
        Notification::assertNotSentTo($outsider, CommentReceived::class);
    }

    public function test_moderator_reply_alerts_owner_and_original_author_without_self_notification(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->publishedDeclaration($owner);
        $question = $declaration->commentaires()->create([
            'user_id' => $outsider->id,
            'contenu' => 'J’ai trouvé un objet similaire.',
        ]);

        $this->actingAs($moderator)->post(route('declarations.commenter', $declaration), [
            'parent_id' => $question->id,
            'contenu' => 'Pouvez-vous préciser le lieu ?',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('commentaires', [
            'declaration_id' => $declaration->id,
            'parent_id' => $question->id,
            'user_id' => $moderator->id,
        ]);
        Notification::assertSentTo($owner, CommentReceived::class, fn ($notification) => $notification->isReply);
        Notification::assertSentTo($outsider, CommentReceived::class, fn ($notification) => $notification->isReply);
        Notification::assertNotSentTo($moderator, CommentReceived::class);
    }

    public function test_cannot_reply_to_another_declarations_comment(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $declaration = $this->publishedDeclaration($owner);
        $other = $this->publishedDeclaration(User::factory()->create());
        $foreignComment = $other->commentaires()->create([
            'user_id' => $owner->id,
            'contenu' => 'Autre dossier',
        ]);

        $this->actingAs($owner)->post(route('declarations.commenter', $declaration), [
            'parent_id' => $foreignComment->id,
            'contenu' => 'Réponse hors dossier',
        ])->assertSessionHasErrors('parent_id');
        $this->assertDatabaseCount('commentaires', 1);
        Notification::assertNothingSent();
    }

    public function test_moderator_can_read_own_alerts_but_not_mark_another_users_alert_read(): void
    {
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $owner = User::factory()->create();
        $declaration = $this->publishedDeclaration($owner);
        $alert = $moderator->appNotifications()->create([
            'declaration_id' => $declaration->id,
            'message' => 'Nouvelle information sur la déclaration',
            'date_envoi' => now(),
            'canal' => 'app',
        ]);

        $this->actingAs($moderator)->get(route('notifications.index'))
            ->assertOk()->assertSee('Nouvelle information');
        $this->actingAs($owner)->post(route('notifications.marquer-lue', $alert))
            ->assertForbidden();
        $this->actingAs($moderator)->post(route('notifications.marquer-lue', $alert))
            ->assertRedirect();
        $this->assertTrue($alert->fresh()->lu);
    }

    public function test_only_moderation_can_delete_comments(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $admin = User::factory()->create(['role' => 'administrateur']);
        $declaration = $this->publishedDeclaration($owner);
        $comment = $declaration->commentaires()->create([
            'user_id' => $owner->id,
            'contenu' => 'Message inapproprié',
        ]);

        $this->actingAs($owner)->delete(route('moderation.commentaires.supprimer', $comment))
            ->assertForbidden();
        $this->assertDatabaseHas('commentaires', ['id' => $comment->id]);

        $this->actingAs($moderator)->delete(route('moderation.commentaires.supprimer', $comment))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('commentaires', ['id' => $comment->id]);

        $second = $declaration->commentaires()->create([
            'user_id' => $owner->id,
            'contenu' => 'Autre message',
        ]);
        $this->actingAs($admin)->delete(route('moderation.commentaires.supprimer', $second))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('commentaires', ['id' => $second->id]);
    }

    public function test_my_declarations_shows_public_photo_preview(): void
    {
        $owner = User::factory()->create();
        $declaration = $this->publishedDeclaration($owner);

        $this->actingAs($owner)->get(route('declarations.index'))
            ->assertOk()
            ->assertSee('src="'.$declaration->photoUrl().'"', false);
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
            'facebook_post_id' => 'fb-test',
            'instagram_post_id' => 'ig-test',
        ]);
        $declaration->piecesJointes()->create([
            'type_document' => 'declaration_perte',
            'disque' => 'local',
            'chemin' => 'declarations-privees/preuve.pdf',
            'nom_original' => 'preuve.pdf',
        ]);

        return $declaration;
    }
}
