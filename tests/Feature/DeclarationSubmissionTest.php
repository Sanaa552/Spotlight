<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\PieceJointe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeclarationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_can_submit_a_declaration_with_a_valid_attachment(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();

        $response = $this->actingAs($citizen)->post(route('declarations.store'), [
            'type' => 'perte',
            'categorie' => 'objet',
            'type_perte' => 'Portefeuille perdu',
            'description' => 'Portefeuille noir perdu au marché central.',
            'lieu' => 'Marché central de Douala',
            'adresse' => 'Marché central, Akwa, Douala',
            'photo_publique' => UploadedFile::fake()->create('portefeuille.jpg', 1024, 'image/jpeg'),
            'declaration_perte' => UploadedFile::fake()->create('declaration-perte.pdf', 2048, 'application/pdf'),
            'pieces_jointes' => [UploadedFile::fake()->create('justificatif.pdf', 1024, 'application/pdf')],
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('declarations.index'));

        $this->assertDatabaseCount('declarations', 1);
        $this->assertDatabaseCount('localisations', 1);
        $this->assertDatabaseCount('pieces_jointes', 2);
        $this->assertDatabaseHas('pieces_jointes', [
            'type_document' => 'declaration_perte',
            'disque' => 'local',
        ]);
        $this->assertDatabaseHas('pieces_jointes', [
            'type_document' => 'piece_jointe',
            'disque' => 'local',
        ]);
        $this->assertNotNull(Declaration::firstOrFail()->photo_path);
        Storage::disk('public')->assertExists(Declaration::firstOrFail()->photo_path);
    }

    public function test_attachment_larger_than_ten_megabytes_is_rejected_in_french(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $citizen = User::factory()->create();

        $response = $this->actingAs($citizen)
            ->from(route('declarations.create'))
            ->post(route('declarations.store'), [
                'type' => 'perte',
                'categorie' => 'objet',
                'type_perte' => 'Portefeuille perdu',
                'description' => 'Portefeuille noir perdu au marché central.',
                'adresse' => 'Marché central, Akwa, Douala',
                'photo_publique' => UploadedFile::fake()->create('portefeuille.jpg', 1024, 'image/jpeg'),
                'declaration_perte' => UploadedFile::fake()->create('declaration-perte.pdf', 1024, 'application/pdf'),
                'pieces_jointes' => [UploadedFile::fake()->create('document.pdf', 11264, 'application/pdf')],
            ]);

        $response
            ->assertRedirect(route('declarations.create'))
            ->assertSessionHasErrors([
                'pieces_jointes.0' => 'Chaque pièce jointe doit peser au maximum 10 Mo.',
            ]);

        $this->assertDatabaseCount('declarations', 0);
        $this->assertDatabaseCount('pieces_jointes', 0);
    }

    public function test_loss_report_is_required_only_for_a_loss(): void
    {
        $citizen = User::factory()->create();

        $lossResponse = $this->actingAs($citizen)
            ->from(route('declarations.create'))
            ->post(route('declarations.store'), [
                'type' => 'perte',
                'categorie' => 'personne',
                'type_perte' => 'Personne disparue',
                'description' => 'Signalement suffisamment détaillé.',
                'adresse' => 'Douala',
                'photo_publique' => UploadedFile::fake()->create('personne.jpg', 1024, 'image/jpeg'),
            ]);

        $lossResponse->assertSessionHasErrors([
            'declaration_perte' => 'Le document de déclaration de perte est obligatoire pour signaler une perte.',
        ]);

        $discoveryResponse = $this->actingAs($citizen)->post(route('declarations.store'), [
            'type' => 'decouverte',
            'categorie' => 'objet',
            'type_decouverte' => 'Téléphone trouvé',
            'description' => 'Téléphone trouvé près du marché.',
            'adresse' => 'Douala',
            'photo_publique' => UploadedFile::fake()->create('telephone.jpg', 1024, 'image/jpeg'),
        ]);

        $discoveryResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseCount('declarations', 1);
    }

    public function test_loss_report_is_private_and_access_controlled(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = User::factory()->create();
        $otherCitizen = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderateur']);

        $this->actingAs($owner)->post(route('declarations.store'), [
            'type' => 'perte',
            'categorie' => 'personne',
            'type_perte' => 'Personne disparue',
            'description' => 'Signalement suffisamment détaillé.',
            'adresse' => 'Douala',
            'photo_publique' => UploadedFile::fake()->create('portrait.jpg', 1024, 'image/jpeg'),
            'declaration_perte' => UploadedFile::fake()->create('declaration-privee.pdf', 1024, 'application/pdf'),
            'pieces_jointes' => [UploadedFile::fake()->create('cni.jpg', 1024, 'image/jpeg')],
        ])->assertSessionHasNoErrors();

        $declaration = Declaration::firstOrFail();
        $declaration->update(['statut' => 'validee']);
        $lossReport = PieceJointe::where('type_document', 'declaration_perte')->firstOrFail();
        $identityDocument = PieceJointe::where('nom_original', 'cni.jpg')->firstOrFail();

        $this->assertSame('local', $identityDocument->disque);
        Storage::disk('local')->assertExists($identityDocument->chemin);
        Storage::disk('public')->assertMissing($identityDocument->chemin);

        $this->actingAs($owner)
            ->get(route('pieces-jointes.telecharger', $lossReport))
            ->assertOk()
            ->assertDownload('declaration-privee.pdf');

        $this->actingAs($otherCitizen)
            ->get(route('pieces-jointes.telecharger', $lossReport))
            ->assertForbidden();
        $this->actingAs($otherCitizen)
            ->get(route('pieces-jointes.telecharger', $identityDocument))
            ->assertForbidden();

        $this->actingAs($moderator)
            ->get(route('pieces-jointes.telecharger', $lossReport))
            ->assertOk();
        $this->actingAs($moderator)
            ->get(route('pieces-jointes.telecharger', $identityDocument))
            ->assertDownload('cni.jpg');

        $this->actingAs($otherCitizen)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('declaration-privee.pdf')
            ->assertDontSee('cni.jpg');

        $this->get(route('public.declarations.index'))
            ->assertOk()
            ->assertDontSee('declaration-privee.pdf')
            ->assertDontSee('cni.jpg')
            ->assertSee('photos-publiques/', false);
    }

    public function test_public_photo_is_required_and_private_attachment_cannot_replace_it(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $citizen = User::factory()->create();

        $this->actingAs($citizen)->post(route('declarations.store'), [
            'type' => 'decouverte',
            'categorie' => 'objet',
            'type_decouverte' => 'Téléphone trouvé',
            'description' => 'Téléphone trouvé près du marché.',
            'adresse' => 'Douala',
            'pieces_jointes' => [UploadedFile::fake()->create('preuve.jpg', 1024, 'image/jpeg')],
        ])->assertSessionHasErrors('photo_publique');

        $this->assertDatabaseCount('declarations', 0);
    }
}
