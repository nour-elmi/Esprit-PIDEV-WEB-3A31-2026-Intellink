<?php

namespace App\DataFixtures;

use App\Entity\Utilisateur; // On utilise bien ton entité Utilisateur
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new Utilisateur();
        $admin->setEmail('admin@intel-link.com');
        $admin->setNom('Super Admin');
        
        // CORRECTION 1 : Ton entité attend un String ('ROLE_ADMIN'), pas un Array (['ROLE_ADMIN'])
        $admin->setRole('ROLE_ADMIN'); 
        
        // CORRECTION 2 : Le champ authMethod est obligatoire dans ton entité
        $admin->setAuthMethod('NONE');

        $admin->setStatutCompte('ACTIF');
        $admin->setImage('default_avatar.png');

        // CORRECTION 3 : Ton entité utilise setMdp() et non setPassword()
        $password = $this->hasher->hashPassword($admin, 'admin123');
        $admin->setMdp($password);

        $manager->persist($admin);
        $manager->flush();
    }
}