<?php

namespace Tests\Unit;

use App\Models\Declaration;
use PHPUnit\Framework\TestCase;

class DeclarationNotificationLabelTest extends TestCase
{
    public function test_label_identifies_the_case_and_normalizes_its_title(): void
    {
        $declaration = new Declaration([
            'type' => 'perte',
            'categorie' => 'objet',
            'type_perte' => "Portefeuille\n brun",
        ]);
        $declaration->id = 8;

        $this->assertSame("perte d'objet «Portefeuille brun» (dossier #8)", $declaration->libelleNotification());
    }

    public function test_label_still_identifies_a_case_without_a_title(): void
    {
        $declaration = new Declaration(['type' => 'decouverte', 'categorie' => 'personne']);
        $declaration->id = 9;

        $this->assertSame('signalement de personne (dossier #9)', $declaration->libelleNotification());
    }
}
