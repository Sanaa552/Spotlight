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
        Notification::assertSentTo($moderator, RapprochementUpdate::class);
        Notification::assertNotSentTo($owner, RapprochementUpdate::class);

        $this->actingAs($finder)->post(route('declarations.store'), [
            ...$data,
            'photo_publique' => UploadedFile::fake()->create('autre.jpg', 1024, 'image/jpeg'),
            'preuve_decouverte' => UploadedFile::fake()->create('autre.mp4', 1024, 'video/mp4'),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('declarations', 3);
        $this->assertDatabaseCount('rapprochements', 1);
    }

    public function test_both_declarants_and_moderator_are_required_for_matched_restitution(): void
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
        Notification::assertSentTo($owner, RapprochementUpdate::class);
        Notification::assertSentTo($finder, RapprochementUpdate::class);

        $this->actingAs($owner)->post(route('declarations.confirmer-restitution', $loss))->assertSessionHas('warning');
        $this->actingAs($owner)->post(route('rapprochements.confirmer', $match))->assertSessionHas('success');
        $this->actingAs($moderator)->post(route('moderation.rapprochements.finaliser', $match))->assertSessionHas('warning');
        $this->actingAs($finder)->post(route('rapprochements.confirmer', $match))->assertSessionHas('success');
        $this->actingAs($moderator)->post(route('moderation.rapprochements.finaliser', $match))->assertSessionHas('success');

        $this->assertSame('cloturee', $loss->fresh()->statut);
        $this->assertSame('cloturee', $found->fresh()->statut);
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
        $this->post(route('moderation.valider', $found), ['preuves_verifiees' => '1'])
            ->assertSessionHas('warning');
        $this->assertDatabaseCount('jobs', 0);
        $this->assertNull($found->fresh()->publication_status);
        $this->post(route('moderation.rapprochements.verifier', $match))
            ->assertSessionHasErrors('correspondance_verifiee');
        $this->post(route('moderation.rapprochements.verifier', $match), ['correspondance_verifiee' => '1'])
            ->assertSessionHas('success');
        $this->assertSame('verifie', $match->fresh()->statut);
        $this->actingAs($finder)->post(route('rapprochements.confirmer', $match))->assertSessionHas('warning');
        $this->actingAs($moderator)->post(route('moderation.rapprochements.finaliser', $match))->assertSessionHas('warning');
        $this->post(route('moderation.valider', $found), ['preuves_verifiees' => '1'])
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
        $this->post(route('moderation.valider', $found), ['preuves_verifiees' => '1'])
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

        $this->actingAs($moderator)->post(route('moderation.valider', $found), ['preuves_verifiees' => '1'])
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
