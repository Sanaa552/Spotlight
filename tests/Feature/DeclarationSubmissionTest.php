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
            'declaration_perte' => UploadedFile::fake()->create('declaration-privee.pdf', 1024, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $declaration = Declaration::firstOrFail();
        $declaration->update(['statut' => 'validee']);
        $lossReport = PieceJointe::where('type_document', 'declaration_perte')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('pieces-jointes.telecharger', $lossReport))
            ->assertOk()
            ->assertDownload('declaration-privee.pdf');

        $this->actingAs($otherCitizen)
            ->get(route('pieces-jointes.telecharger', $lossReport))
            ->assertForbidden();

        $this->actingAs($moderator)
            ->get(route('pieces-jointes.telecharger', $lossReport))
            ->assertOk();

        $this->actingAs($otherCitizen)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('declaration-privee.pdf');
    }
}
