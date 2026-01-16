<?php

namespace App\DataFixtures;

use App\Entity\Partner;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Tableau de données : Nom => Email
        $partenairesData = [
            'Ryan' => 'ryanhaddadpro@gmail.com',
            'Kousseila' => 'azni.kousseila7@gmail.com',
            'Yanis' => 'yanis.bekhti5@gmail.com',
        ];

        foreach ($partenairesData as $nom => $email) {
            // On vérifie si le partenaire existe déjà pour éviter les doublons
            $existing = $manager->getRepository(Partner::class)->findOneBy(['email' => $email]);

            if (!$existing) {
                $partner = new Partner();
                $partner->setName($nom);
                $partner->setEmail($email);

                $manager->persist($partner);
            }
        }

        $manager->flush();
    }
}
