<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\Rapprochement;
use App\Models\User;
use App\Notifications\RapprochementUpdate;
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
        $this->actingAs($moderator)->post(route('moderation.rapprochements.verifier', $match))->assertSessionHas('success');
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
