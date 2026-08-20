<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Declaration;
use App\Models\Localisation;
use App\Models\PieceJointe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ExempleDeclarationsSeeder extends Seeder
{
    public function run(): void
    {
        // ----- Comptes fictifs -----
        $moderateur = User::firstOrCreate(
            ['email' => 'moderateur.demo@spotlight.cm'],
            [
                'name' => 'Modérateur Démo',
                'telephone' => '699000000',
                'password' => Hash::make('password'),
                'role' => Role::Moderateur,
            ]
        );

        $prenoms = ['Aïcha', 'Junior', 'Étienne', 'Marceline', 'Nadège', 'Delphine', 'Paul', 'Samira', 'Eric', 'Florence'];
        $citoyens = collect($prenoms)->map(function (string $prenom, int $i) {
            return User::firstOrCreate(
                ['email' => 'citoyen.demo'.$i.'@spotlight.cm'],
                [
                    'name' => $prenom.' Demo',
                    'telephone' => '69900'.str_pad($i, 4, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'role' => Role::Citoyen,
                ]
            );
        });

        // ----- Données des déclarations d'exemple -----
        $exemples = [
            // Pertes (validées)
            ['type' => 'perte', 'statut' => 'validee', 'categorie' => 'personne', 'type_perte' => 'Personne disparue', 'description' => "Fatima Mba, disparue depuis le 12 août 2026 au marché central. Portait un voile noir et une robe sombre.", 'ville' => 'Douala', 'photo' => 'portrait_femme_hijab.png'],
            ['type' => 'perte', 'statut' => 'validee', 'categorie' => 'personne', 'type_perte' => 'Enfant disparu', 'description' => "Jean Mpem Mpem, 8 ans, perdu de vue près de l'école primaire vers 16h. Porte un polo rayé bleu et blanc.", 'ville' => 'Yaoundé', 'photo' => 'portrait_garcon.png'],
            ['type' => 'perte', 'statut' => 'validee', 'categorie' => 'personne', 'type_perte' => 'Personne disparue', 'description' => "Adolphe Nkounga n'est pas rentré du travail depuis 3 jours. Costume bleu marine, cravate.", 'ville' => 'Bafoussam', 'photo' => 'portrait_homme_costume.png'],
            ['type' => 'perte', 'statut' => 'validee', 'categorie' => 'personne', 'type_perte' => 'Personne âgée disparue', 'description' => "Sortie faire une course et non revenue. Porte un foulard traditionnel bleu.", 'ville' => 'Garoua', 'photo' => 'portrait_femme_agee.png'],
            ['type' => 'perte', 'statut' => 'validee', 'categorie' => 'objet', 'type_perte' => 'Objet perdu', 'description' => "Portefeuille en cuir marron perdu dans un taxi, contient des papiers importants.", 'ville' => 'Yaoundé', 'photo' => 'objet_portefeuille2.png'],

            // Découvertes (validées)
            ['type' => 'decouverte', 'statut' => 'validee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Téléphone trouvé sur un siège d'agence de voyage.", 'ville' => 'Douala', 'photo' => 'objet_iphone.png'],
            ['type' => 'decouverte', 'statut' => 'validee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Trousseau de clés de voiture trouvé près d'un parking.", 'ville' => 'Yaoundé', 'photo' => 'objet_cles_voiture.png'],
            ['type' => 'decouverte', 'statut' => 'validee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Sac à dos noir retrouvé dans un bus interurbain.", 'ville' => 'Bafoussam', 'photo' => 'objet_sac_dos.png'],
            ['type' => 'decouverte', 'statut' => 'validee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Montre connectée trouvée à la salle de sport.", 'ville' => 'Garoua', 'photo' => 'objet_montre.png'],
            ['type' => 'decouverte', 'statut' => 'validee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Lunettes de vue retrouvées dans une salle de classe.", 'ville' => 'Douala', 'photo' => 'objet_lunettes.png'],
            ['type' => 'decouverte', 'statut' => 'validee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Ordinateur portable oublié dans un cybercafé.", 'ville' => 'Yaoundé', 'photo' => 'objet_laptop.png'],
            ['type' => 'decouverte', 'statut' => 'validee', 'categorie' => 'personne', 'type_decouverte' => 'Personne retrouvée errante', 'description' => "Homme âgé retrouvé désorienté près du marché, ne se souvient pas de son adresse.", 'ville' => 'Ngaoundéré', 'photo' => 'portrait_homme_age.png'],

            // Restitutions (clôturées)
            ['type' => 'perte', 'statut' => 'cloturee', 'categorie' => 'personne', 'type_perte' => 'Personne disparue', 'description' => "Nina-Marie Leumi a été retrouvée saine et sauve chez un membre de la famille. Merci à tous ceux qui ont partagé l'alerte.", 'ville' => 'Douala', 'photo' => 'portrait_femme_tresses.png'],
            ['type' => 'perte', 'statut' => 'cloturee', 'categorie' => 'personne', 'type_perte' => 'Personne disparue', 'description' => "Mekem Paul a été retrouvé après 2 jours, en bonne santé.", 'ville' => 'Bafoussam', 'photo' => 'portrait_homme_tshirt.png'],
            ['type' => 'perte', 'statut' => 'cloturee', 'categorie' => 'personne', 'type_perte' => 'Personne disparue', 'description' => "Bibiche Ngoungoure a été retrouvée grâce à un témoignage reçu via l'application.", 'ville' => 'Yaoundé', 'photo' => 'portrait_femme_courte.png'],
            ['type' => 'decouverte', 'statut' => 'cloturee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Sac à main restitué à sa propriétaire le jour même.", 'ville' => 'Yaoundé', 'photo' => 'objet_sac_main.png'],
            ['type' => 'decouverte', 'statut' => 'cloturee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Écouteurs sans fil restitués à leur propriétaire.", 'ville' => 'Douala', 'photo' => 'objet_airpods.png'],
            ['type' => 'decouverte', 'statut' => 'cloturee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Téléphone restitué grâce à l'application, contact établi en moins de 24h.", 'ville' => 'Garoua', 'photo' => 'objet_telephone.png'],
            ['type' => 'decouverte', 'statut' => 'cloturee', 'categorie' => 'objet', 'type_decouverte' => 'Objet trouvé', 'description' => "Trousseau de clés et portefeuille restitués à leur propriétaire.", 'ville' => 'Douala', 'photo' => 'objet_cles_portefeuille.png'],
        ];

        foreach ($exemples as $i => $e) {
            $citoyen = $citoyens[$i % $citoyens->count()];

            $declaration = Declaration::create([
                'user_id' => $citoyen->id,
                'moderateur_id' => $moderateur->id,
                'type' => $e['type'],
                'categorie' => $e['categorie'],
                'description' => $e['description'],
                'lieu' => $e['ville'],
                'statut' => $e['statut'],
                'type_perte' => $e['type_perte'] ?? null,
                'type_decouverte' => $e['type_decouverte'] ?? null,
                'cloturee_at' => $e['statut'] === 'cloturee' ? now()->subDays(random_int(1, 20)) : null,
                'created_at' => now()->subDays(random_int(1, 30)),
            ]);

            Localisation::create([
                'declaration_id' => $declaration->id,
                'adresse' => $e['ville'].', Cameroun',
            ]);

            $cheminRelatif = 'exemples/'.$e['photo'];
            if (Storage::disk('public')->exists($cheminRelatif)) {
                PieceJointe::create([
                    'declaration_id' => $declaration->id,
                    'chemin' => $cheminRelatif,
                    'nom_original' => $e['photo'],
                    'type_mime' => 'image/png',
                    'taille' => Storage::disk('public')->size($cheminRelatif),
                ]);
            }
        }
    }
}