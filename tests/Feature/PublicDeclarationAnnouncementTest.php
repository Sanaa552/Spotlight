<?php

namespace Tests\Feature;

use App\Jobs\AnnouncePublishedDeclaration;
use App\Models\Declaration;
use App\Models\User;
use App\Notifications\NewPublicDeclaration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicDeclarationAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_citizen_can_open_public_detail_without_private_documents(): void
    {
        $owner = User::factory()->create();
        $facebookCitizen = User::factory()->create(['facebook_id' => 'facebook-123']);
        $declaration = $this->publishedDeclaration($owner);

        $declaration->piecesJointes()->create([
            'type_document' => 'piece_jointe',
            'disque' => 'local',
            'chemin' => 'declarations-privees/passport.pdf',
            'nom_original' => 'passport-secret.pdf',
        ]);

        $this->actingAs($facebookCitizen)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('public.declarations.show', $declaration));
        $this->actingAs($facebookCitizen)->get(route('public.declarations.show', $declaration))
            ->assertOk()
            ->assertSee('Sac noir perdu')
            ->assertDontSee('passport-secret.pdf')
            ->assertDontSee('declarations-privees');
        $this->actingAs($facebookCitizen)->get(route('declarations.show', $declaration))
            ->assertForbidden();
        $this->get(route('public.declarations.show', $declaration))->assertOk();
    }

    public function test_public_cards_keep_bounded_previews_while_detail_shows_full_description(): void
    {
        $owner = User::factory()->create();
        $declaration = $this->publishedDeclaration($owner);
        $description = str_repeat('Description détaillée du sac perdu. ', 25);
        $declaration->update(['description' => $description]);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()->assertSee('line-clamp-4')->assertSee('h-56 w-full bg-gray-50 object-contain')
            ->assertSee('Voir le détail');
        $this->get(route('public.declarations.index', ['onglet' => 'pertes']))
            ->assertOk()->assertSee('h-[26rem]')->assertSee('line-clamp-3')
            ->assertSee('Voir le détail');
        $this->get(route('public.declarations.show', $declaration))
            ->assertOk()->assertSee($description);
    }

    public function test_pending_or_private_declarations_have_no_public_detail(): void
    {
        $owner = User::factory()->create();
        $pending = $this->publishedDeclaration($owner);
        $pending->update(['statut' => 'en_attente']);
        $this->get(route('public.declarations.show', $pending))->assertNotFound();

        $private = $this->publishedDeclaration($owner);
        $private->update(['facebook_post_id' => null]);
        $this->get(route('public.declarations.show', $private))->assertNotFound();
    }

    public function test_publication_alerts_verified_citizens_only_and_respects_email_preference(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $subscriber = User::factory()->create();
        $muted = User::factory()->create(['new_declaration_email' => false]);
        $blocked = User::factory()->create(['is_blocked' => true]);
        $unverified = User::factory()->unverified()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $declaration = $this->publishedDeclaration($owner);

        (new AnnouncePublishedDeclaration($declaration->id))->handle();
        (new AnnouncePublishedDeclaration($declaration->id))->handle();

        $this->assertDatabaseCount('app_notifications', 2);
        foreach ([$subscriber, $muted] as $citizen) {
            $this->assertDatabaseHas('app_notifications', [
                'user_id' => $citizen->id,
                'declaration_id' => $declaration->id,
            ]);
        }
        Notification::assertSentToTimes($subscriber, NewPublicDeclaration::class, 1);
        foreach ([$owner, $muted, $blocked, $unverified, $moderator] as $user) {
            Notification::assertNotSentTo($user, NewPublicDeclaration::class);
        }
    }

    public function test_no_announcement_before_both_meta_posts_are_confirmed(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        User::factory()->create();
        $declaration = $this->publishedDeclaration($owner);
        $declaration->update(['instagram_post_id' => null]);

        (new AnnouncePublishedDeclaration($declaration->id))->handle();

        $this->assertDatabaseCount('app_notifications', 0);
        Notification::assertNothingSent();
    }

    public function test_published_discovery_of_an_object_is_announced(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $subscriber = User::factory()->create();
        $declaration = $owner->declarations()->create([
            'type' => 'decouverte',
            'categorie' => 'objet',
            'type_decouverte' => 'Portefeuille trouvé',
            'description' => 'Portefeuille trouvé près du marché.',
            'photo_path' => 'photos-publiques/portefeuille.jpg',
            'statut' => 'validee',
            'facebook_post_id' => 'fb-found',
            'instagram_post_id' => 'ig-found',
        ]);
        foreach (['preuve_decouverte', 'preuve_signalement'] as $proofType) {
            $declaration->piecesJointes()->create([
                'type_document' => $proofType,
                'disque' => 'local',
                'chemin' => "declarations-privees/{$proofType}.pdf",
                'nom_original' => "{$proofType}.pdf",
            ]);
        }

        (new AnnouncePublishedDeclaration($declaration->id))->handle();

        $this->get(route('public.declarations.show', $declaration))->assertOk()
            ->assertDontSee('preuve_signalement.pdf');
        Notification::assertSentTo($subscriber, NewPublicDeclaration::class, fn ($notification) => $notification->type === 'decouverte');
    }

    private function publishedDeclaration(User $owner): Declaration
    {
        $declaration = $owner->declarations()->create([
            'type' => 'perte',
            'categorie' => 'objet',
            'type_perte' => 'Sac noir',
            'description' => 'Sac noir perdu',
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
