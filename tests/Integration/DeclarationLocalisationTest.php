<?php

namespace Tests\Integration;

use App\Models\Declaration;
use App\Models\Localisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeclarationLocalisationTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_declaration_peut_etre_associee_a_une_localisation(): void
    {
        $utilisateur = User::factory()->create();

        $declaration = Declaration::create([
            'user_id' => $utilisateur->id,
            'type' => 'perte',
            'categorie' => 'objet',
            'description' => 'Téléphone perdu',
            'lieu' => 'Akwa, Douala',
            'statut' => 'en_attente',
        ]);

        $localisation = Localisation::create([
            'declaration_id' => $declaration->id,
            'adresse' => 'Akwa, Douala',
            'latitude' => 4.0511,
            'longitude' => 9.7679,
        ]);

        $this->assertDatabaseHas('localisations', [
            'declaration_id' => $declaration->id,
            'adresse' => 'Akwa, Douala',
        ]);

        $this->assertTrue(
            $declaration->localisation->is($localisation)
        );
    }

    public function test_une_localisation_fournit_correctement_ses_coordonnees(): void
    {
        $utilisateur = User::factory()->create();

        $declaration = Declaration::create([
            'user_id' => $utilisateur->id,
            'type' => 'perte',
            'categorie' => 'objet',
            'description' => 'Téléphone perdu',
            'statut' => 'en_attente',
        ]);

        $localisation = Localisation::create([
            'declaration_id' => $declaration->id,
            'adresse' => 'Bonamoussadi, Douala',
            'latitude' => 4.0950,
            'longitude' => 9.7350,
        ]);

        $coordonnees = $localisation->fournir();

        $this->assertSame('Bonamoussadi, Douala', $coordonnees['adresse']);
        $this->assertEquals(4.0950, $coordonnees['latitude']);
        $this->assertEquals(9.7350, $coordonnees['longitude']);
    }
}