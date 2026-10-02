<?php

namespace Tests\Feature;

use App\Jobs\PublishDeclaration;
use App\Models\Declaration;
use App\Models\Rapprochement;
use App\Models\User;
use App\Notifications\RapprochementUpdate;
use App\Services\MetaPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RapprochementFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_object_discovery_can_propose_a_public_loss_or_remain_independent(): void
    {
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        $owner = User::factory()->create();
        $finder = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $loss = $this->loss($owner);

        $this->actingAs($finder)->get(route('declarations.create'))
            ->assertOk()->assertSee('station-picker')->assertSee('loss-picker')
            ->assertSee('Aucune annonce choisie');
        $this->actingAs($finder)->get(route('rapprochements.pertes', ['format' => 'json']))
            ->assertOk()->assertJsonPath('pertes.0.id', $loss->id);

        $data = [
            'type' => 'decouverte', 'categorie' => 'objet',
            'type_decouverte' => 'Sac trouvé', 'description' => 'Sac trouvé au marché.',
            'adresse' => 'Douala', 'photo_publique' => UploadedFile::fake()->create('sac.jpg', 1024, 'image/jpeg'),
            'preuve_decouverte' => UploadedFile::fake()->create('lieu.mp4', 1024, 'video/mp4'),
        ];
        $this->actingAs($finder)->post(route('declarations.store'), [...$data, 'perte_id' => $loss->id])
            ->assertSessionHasNoErrors();
        $found = Declaration::query()->where('type', 'decouverte')->firstOrFail();
        $this->assertDatabaseHas('rapprochements', ['perte_id' => $loss->id, 'decouverte_id' => $found->id, 'statut' => 'propose']);
        Notification::assertSentTo($moderator, RapprochementUpdate::class,
            fn ($notification) => str_contains($notification->subject, 'Sac rouge')
                && str_contains($notification->message, 'Sac trouvé')
                && str_contains($notification->message, $owner->name)
                && str_contains($notification->message, $finder->name));
        Notification::assertNotSentTo($owner, RapprochementUpdate::class);

        $this->actingAs($finder)->post(route('declarations.store'), [
            ...$data,
            'photo_publique' => UploadedFile::fake()->create('autre.jpg', 1024, 'image/jpeg'),
            'preuve_decouverte' => UploadedFile::fake()->create('autre.mp4', 1024, 'video/mp4'),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('declarations', 3);
        $this->assertDatabaseCount('rapprochements', 1);
    }

    public function test_owner_confirmation_and_moderator_review_finalize_without_finder_approval(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $finder = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $loss = $this->loss($owner);
        $found = $this->found($finder);
        $match = Rapprochement::create(['perte_id' => $loss->id, 'decouverte_id' => $found->id]);

        $this->actingAs($finder)->post(route('moderation.rapprochements.verifier', $match))->assertForbidden();
        $this->actingAs($moderator)->post(route('moderation.rapprochements.verifier', $match), ['correspondance_verifiee' => '1'])->assertSessionHas('success');
        $this->assertSame('verifie', $match->fresh()->statut);
        Notification::assertSentTo($owner, RapprochementUpdate::class,
            fn ($notification) => str_contains($notification->subject, 'Sac rouge')
                && str_contains($notification->message, "(dossier #{$loss->id})"));
        Notification::assertSentTo($finder, RapprochementUpdate::class,
            fn ($notification) => str_contains($notification->subject, 'Sac trouvé')
                && str_contains($notification->message, "(dossier #{$found->id})"));

        $this->actingAs($owner)->post(route('declarations.confirmer-restitution', $loss))->assertSessionHas('warning');
        $review = ['restitution_verifiee' => '1', 'restitution_note' => 'Remise contrôlée auprès du poste identifié.'];
        $this->actingAs($moderator)->post(route('moderation.rapprochements.finaliser', $match), $review)->assertSessionHas('warning');
        $this->actingAs($owner)->post(route('rapprochements.confirmer', $match))->assertSessionHas('success');
        Notification::assertSentTo($moderator, RapprochementUpdate::class,
            fn ($notification) => str_contains($notification->message, 'confirme avoir récupéré'));
        $this->actingAs($moderator)->post(route('moderation.rapprochements.finaliser', $match), $review)->assertSessionHas('success');

        $this->assertSame('cloturee', $loss->fresh()->statut);
        $this->assertSame('cloturee', $found->fresh()->statut);
        $this->assertNull($match->fresh()->decouvreur_confirme_at);
        $this->assertDatabaseHas('rapprochements', ['id' => $match->id, 'statut' => 'restitue', 'restitution_note' => $review['restitution_note']]);
        $this->assertDatabaseHas('publication_reminders', ['declaration_id' => $loss->id, 'kind' => 'restitution', 'channel' => 'facebook', 'status' => 'queued']);
        $this->assertDatabaseHas('publication_reminders', ['declaration_id' => $loss->id, 'kind' => 'restitution', 'channel' => 'instagram', 'status' => 'queued']);
        $this->actingAs($moderator)->post(route('moderation.rapprochements.finaliser', $match), $review)->assertSessionHas('warning');
        $this->assertDatabaseCount('publication_reminders', 2);
        $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('Sac rouge');
        $this->get(route('public.declarations.index', ['onglet' => 'restitutions']))->assertSee('Sac rouge');
    }

    public function test_person_discovery_cannot_be_matched_publicly_and_invalid_loss_is_rejected(): void
    {
        $owner = User::factory()->create();
        $finder = User::factory()->create();
        $loss = $this->loss($owner);
        $loss->update(['categorie' => 'personne']);
        $person = $finder->declarations()->create([
            'type' => 'decouverte', 'categorie' => 'personne',
            'description' => 'Signalement privé.', 'statut' => 'en_attente',
        ]);

        $this->actingAs($finder)->get(route('rapprochements.pertes', ['decouverte' => $person->id]))->assertForbidden();
        $this->actingAs($finder)->get(route('declarations.create', ['perte_id' => $loss->id]))
            ->assertOk()->assertSee('Aucune annonce choisie');
        $this->get(route('public.declarations.show', $person))->assertNotFound();
    }

    public function test_localized_loss_stays_public_but_cannot_be_selected_again(): void
    {
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        $owner = User::factory()->create();
        $finder = User::factory()->create();
        $otherFinder = User::factory()->create();
        $loss = $this->loss($owner);
        $found = $this->found($finder);
        $found->update([
            'poste_verifie_nom' => 'Commissariat de Bonanjo, Douala',
            'poste_verifie_at' => now(),
        ]);
        $match = Rapprochement::create([
            'perte_id' => $loss->id, 'decouverte_id' => $found->id, 'statut' => 'propose',
        ]);

        $this->actingAs($otherFinder)->get(route('rapprochements.pertes', ['format' => 'json']))
            ->assertOk()->assertJsonCount(1, 'pertes');
        $match->update(['statut' => 'verifie']);

        $this->get(route('rapprochements.pertes', ['format' => 'json']))
            ->assertOk()->assertJsonCount(0, 'pertes');
        $this->get(route('declarations.create', ['perte_id' => $loss->id]))
            ->assertOk()->assertSee('Aucune annonce choisie');
        $this->get(route('public.declarations.show', $loss))
            ->assertOk()->assertSee('Localisé, non restitué')->assertDontSee('J’ai retrouvé cet objet');
        $this->get(route('dashboard'))
            ->assertOk()->assertSee('Découvertes')->assertSee('Restitutions')->assertSee('Sac trouvé');
        $this->get(route('public.declarations.index', ['onglet' => 'decouvertes']))
            ->assertOk()->assertSee('Sac trouvé');

        $anotherDiscovery = $this->found($otherFinder);
        $this->post(route('rapprochements.proposer', $anotherDiscovery), ['perte_id' => $loss->id])
            ->assertSessionHas('warning');
        $this->post(route('declarations.store'), [
            'type' => 'decouverte', 'categorie' => 'objet', 'type_decouverte' => 'Autre sac trouvé',
            'description' => 'Sac trouvé dans la rue.', 'adresse' => 'Douala', 'perte_id' => $loss->id,
            'photo_publique' => UploadedFile::fake()->create('sac.jpg', 100, 'image/jpeg'),
            'preuve_decouverte' => UploadedFile::fake()->create('lieu.mp4', 100, 'video/mp4'),
        ])->assertSessionHasErrors('perte_id');
        $this->assertDatabaseCount('rapprochements', 1);

        $loss->update(['statut' => 'cloturee']);
        $found->update(['statut' => 'cloturee']);
        $match->update(['statut' => 'restitue']);
        $this->get(route('public.declarations.index', ['onglet' => 'restitutions']))
            ->assertOk()->assertSee('Sac rouge')->assertSee('Sac trouvé');
        $this->get(route('public.declarations.index', ['onglet' => 'decouvertes']))
            ->assertOk()->assertDontSee(route('public.declarations.show', $found), false);
        $this->get(route('public.declarations.show', $loss))->assertOk();
    }

    public function test_linked_discovery_is_reviewed_before_publication_and_handover_waits(): void
    {
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        $owner = User::factory()->create();
        $finder = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $loss = $this->loss($owner);
        $found = $this->pendingFound($finder);
        $match = Rapprochement::create(['perte_id' => $loss->id, 'decouverte_id' => $found->id]);

        $this->actingAs($moderator)->get(route('moderation.declarations.show', $found))
            ->assertOk()->assertSee('Vérifiez ou refusez d’abord la correspondance');
        $this->post(route('moderation.valider', $found), $this->verifiedDepot())
            ->assertSessionHas('warning');
        $this->assertDatabaseCount('jobs', 0);
        $this->assertNull($found->fresh()->publication_status);
        $this->post(route('moderation.rapprochements.verifier', $match))
            ->assertSessionHasErrors('correspondance_verifiee');
        $this->post(route('moderation.rapprochements.verifier', $match), ['correspondance_verifiee' => '1'])
            ->assertSessionHas('success');
        $this->assertSame('verifie', $match->fresh()->statut);
        $this->actingAs($finder)->post(route('rapprochements.confirmer', $match))->assertSessionHas('warning');
        $this->actingAs($moderator)->post(route('moderation.rapprochements.finaliser', $match), [
            'restitution_verifiee' => '1', 'restitution_note' => 'Aucune remise confirmée pour le moment.',
        ])->assertSessionHas('warning');
        $this->post(route('moderation.valider', $found), $this->verifiedDepot())
            ->assertSessionHas('success');
        $this->assertSame('queued', $found->fresh()->publication_status);
        $this->assertDatabaseCount('jobs', 1);

        $meta = \Mockery::mock(MetaPublishingService::class);
        $meta->shouldReceive('publishToFacebook')->once()
            ->andReturn(['success' => true, 'response' => ['id' => 'fb-found']]);
        $meta->shouldReceive('facebookPhotoUrl')->once()->with('fb-found')
            ->andReturn(['success' => true, 'url' => 'https://scontent.example.test/trouve.jpg']);
        $meta->shouldReceive('publishToInstagram')->once()
            ->andReturn(['success' => true, 'response' => ['id' => 'ig-found']]);
        $meta->shouldReceive('publicPostUrl')->twice()
            ->andReturnUsing(fn ($channel) => "https://example.test/{$channel}-post");
        (new PublishDeclaration($found->id, $moderator->id))->handle($meta);

        $this->assertSame('validee', $found->fresh()->statut);
        $this->assertSame('succeeded', $found->fresh()->publication_status);
        $this->assertSame('validee', $loss->fresh()->statut);
        $this->assertSame('verifie', $match->fresh()->statut);
        $this->assertSame('Commissariat de Bonanjo, Douala', $found->fresh()->poste_verifie_nom);
        Notification::assertSentTo($owner, RapprochementUpdate::class,
            fn ($notification) => str_contains($notification->message, 'Commissariat de Bonanjo'));
        $this->get(route('public.declarations.show', $loss))->assertSee('Localisé, non restitué');
    }

    public function test_documentary_review_publishes_with_cautious_wording_and_notifies_owner(): void
    {
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        $owner = User::factory()->create();
        $finder = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $loss = $this->loss($owner);
        $found = $this->pendingFound($finder);
        $match = Rapprochement::create(['perte_id' => $loss->id, 'decouverte_id' => $found->id]);

        $this->actingAs($moderator)->post(route('moderation.rapprochements.verifier', $match), ['correspondance_verifiee' => '1'])
            ->assertSessionHas('success');
        $this->get(route('moderation.declarations.show', $found))
            ->assertOk()->assertSee('Examen du justificatif transmis');
        $review = [
            'preuves_verifiees' => '1',
            'depot_verifie' => '1',
            'poste_verifie_nom' => 'Commissariat de Bonanjo, Douala',
            'poste_verification_methode' => 'documentaire',
            'poste_verification_note' => 'Récépissé du 2 octobre examiné ; poste et date cohérents, présence actuelle non vérifiée.',
        ];
        $this->post(route('moderation.valider', $found), $review)->assertSessionHas('success');
        $this->assertSame('documentaire', $found->fresh()->poste_verification_methode);

        $meta = \Mockery::mock(MetaPublishingService::class);
        $meta->shouldReceive('publishToFacebook')->once()
            ->withArgs(fn ($message, $imageUrl) => str_contains($message, 'justificatif indiquant un dépôt')
                && str_contains($message, 'pas été confirmée directement')
                && ! str_contains($message, 'Objet localisé et déposé')
                && filled($imageUrl))
            ->andReturn(['success' => true, 'response' => ['id' => 'fb-document']]);
        $meta->shouldReceive('facebookPhotoUrl')->once()->with('fb-document')
            ->andReturn(['success' => true, 'url' => 'https://scontent.example.test/trouve.jpg']);
        $meta->shouldReceive('publishToInstagram')->once()
            ->withArgs(fn ($imageUrl, $caption) => str_contains($caption, 'justificatif indiquant un dépôt')
                && str_contains($caption, 'pas été confirmée directement'))
            ->andReturn(['success' => true, 'response' => ['id' => 'ig-document']]);
        $meta->shouldReceive('publicPostUrl')->twice()
            ->andReturnUsing(fn ($channel) => "https://example.test/{$channel}-post");
        (new PublishDeclaration($found->id, $moderator->id))->handle($meta);

        $this->assertSame('validee', $found->fresh()->statut);
        $this->assertTrue($loss->fresh()->aDecouverteDocumentee());
        Notification::assertSentTo($owner, RapprochementUpdate::class,
            fn ($notification) => str_contains($notification->message, "n'a pas confirmé directement"));
        $this->actingAs($owner)->get(route('declarations.show', $loss))
            ->assertOk()->assertSee('Poste indiqué sur le justificatif')
            ->assertSee('Confirmer que j’ai récupéré mon objet');
        $this->get(route('public.declarations.show', $loss))
            ->assertOk()->assertSee('Dépôt documenté, non restitué');
        $this->get(route('public.declarations.show', $found))
            ->assertOk()->assertSee('La présence actuelle de l’objet n’a pas été confirmée directement')
            ->assertDontSee('Dépôt confirmé auprès de');
    }

    public function test_rejected_match_allows_independent_discovery_publication(): void
    {
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        $loss = $this->loss(User::factory()->create());
        $found = $this->pendingFound(User::factory()->create());
        $moderator = User::factory()->create(['role' => 'moderateur']);
        $match = Rapprochement::create(['perte_id' => $loss->id, 'decouverte_id' => $found->id]);

        $this->actingAs($moderator)->post(route('moderation.rapprochements.rejeter', $match))
            ->assertSessionHas('success');
        $this->post(route('moderation.valider', $found), $this->verifiedDepot())
            ->assertSessionHas('success');
        $this->assertSame('rejete', $match->fresh()->statut);
        $this->assertSame('queued', $found->fresh()->publication_status);
    }

    public function test_finder_cannot_add_match_while_discovery_is_being_published(): void
    {
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        $loss = $this->loss(User::factory()->create());
        $finder = User::factory()->create();
        $found = $this->pendingFound($finder);
        $moderator = User::factory()->create(['role' => 'moderateur']);

        $this->actingAs($moderator)->post(route('moderation.valider', $found), $this->verifiedDepot())
            ->assertSessionHas('success');
        $this->actingAs($finder)->post(route('rapprochements.proposer', $found), ['perte_id' => $loss->id])
            ->assertSessionHas('warning');
        $this->assertDatabaseCount('rapprochements', 0);
    }

    public function test_worker_does_not_call_meta_if_an_unreviewed_match_exists(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $loss = $this->loss(User::factory()->create());
        $found = $this->pendingFound(User::factory()->create());
        $moderator = User::factory()->create(['role' => 'moderateur']);
        Rapprochement::create(['perte_id' => $loss->id, 'decouverte_id' => $found->id]);
        $found->update(['publication_status' => 'queued']);

        $meta = \Mockery::mock(MetaPublishingService::class);
        $meta->shouldNotReceive('publishToFacebook');
        $meta->shouldNotReceive('publishToInstagram');
        (new PublishDeclaration($found->id, $moderator->id))->handle($meta);

        $this->assertSame('en_attente', $found->fresh()->statut);
        $this->assertSame('failed', $found->fresh()->publication_status);
        $this->assertNull($found->fresh()->facebook_post_id);
    }

    private function pendingFound(User $finder): Declaration
    {
        $found = $finder->declarations()->create([
            'type' => 'decouverte', 'categorie' => 'objet', 'type_decouverte' => 'Sac trouvé',
            'description' => 'Sac trouvé au marché.', 'photo_path' => 'photos-publiques/trouve.jpg',
            'statut' => 'en_attente',
        ]);
        Storage::disk('public')->put($found->photo_path, 'photo');
        foreach (['preuve_decouverte', 'preuve_signalement'] as $type) {
            $path = 'declarations-privees/'.$type.'.pdf';
            Storage::disk('local')->put($path, 'preuve');
            $found->piecesJointes()->create([
                'type_document' => $type, 'disque' => 'local',
                'chemin' => $path, 'nom_original' => $type.'.pdf',
            ]);
        }

        return $found;
    }

    private function verifiedDepot(): array
    {
        return [
            'preuves_verifiees' => '1',
            'depot_verifie' => '1',
            'poste_verifie_nom' => 'Commissariat de Bonanjo, Douala',
            'poste_verification_methode' => 'appel',
            'poste_verification_note' => 'Dépôt confirmé auprès du poste de Bonanjo.',
        ];
    }

    private function loss(User $owner): Declaration
    {
        $loss = $owner->declarations()->create([
            'type' => 'perte', 'categorie' => 'objet', 'type_perte' => 'Sac rouge',
            'description' => 'Sac rouge perdu.', 'photo_path' => 'photos-publiques/sac.jpg',
            'statut' => 'validee', 'facebook_post_id' => 'fb-test', 'instagram_post_id' => 'ig-test',
        ]);
        $loss->piecesJointes()->create([
            'type_document' => 'declaration_perte', 'disque' => 'local',
            'chemin' => 'declarations-privees/preuve.pdf', 'nom_original' => 'preuve.pdf',
        ]);

        return $loss;
    }

    private function found(User $finder): Declaration
    {
        $found = $finder->declarations()->create([
            'type' => 'decouverte', 'categorie' => 'objet', 'type_decouverte' => 'Sac trouvé',
            'description' => 'Sac trouvé.', 'photo_path' => 'photos-publiques/trouve.jpg',
            'statut' => 'validee', 'facebook_post_id' => 'fb-found', 'instagram_post_id' => 'ig-found',
        ]);
        foreach (['preuve_decouverte', 'preuve_signalement'] as $type) {
            $found->piecesJointes()->create([
                'type_document' => $type, 'disque' => 'local',
                'chemin' => 'declarations-privees/'.$type.'.pdf', 'nom_original' => $type.'.pdf',
            ]);
        }

        return $found;
    }
}
