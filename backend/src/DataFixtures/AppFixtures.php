<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $this->loadCategories($manager);
        $this->loadDemoUsers($manager);
        $manager->flush();
    }

    private function loadCategories(ObjectManager $manager): void
    {
        $categories = [
            ['name' => 'Repas & Restaurant',    'icon' => '🍽️',  'color' => '#f97316'],
            ['name' => 'Transport',              'icon' => '🚗',  'color' => '#3b82f6'],
            ['name' => 'Hébergement',            'icon' => '🏨',  'color' => '#8b5cf6'],
            ['name' => 'Matériel & Équipement',  'icon' => '🖥️',  'color' => '#06b6d4'],
            ['name' => 'Téléphone & Internet',   'icon' => '📱',  'color' => '#10b981'],
            ['name' => 'Formation',              'icon' => '📚',  'color' => '#f59e0b'],
            ['name' => 'Marketing & Pub',        'icon' => '📣',  'color' => '#ec4899'],
            ['name' => 'Fournitures bureau',     'icon' => '📎',  'color' => '#64748b'],
            ['name' => 'Santé',                  'icon' => '🏥',  'color' => '#ef4444'],
            ['name' => 'Divertissement',         'icon' => '🎬',  'color' => '#a855f7'],
            ['name' => 'Courses & Alimentation', 'icon' => '🛒',  'color' => '#84cc16'],
            ['name' => 'Abonnements',            'icon' => '🔄',  'color' => '#0ea5e9'],
            ['name' => 'Autre',                  'icon' => '📦',  'color' => '#94a3b8'],
        ];

        foreach ($categories as $data) {
            $category = new Category();
            $category->setName($data['name']);
            $category->setIcon($data['icon']);
            $category->setColor($data['color']);
            $manager->persist($category);
        }
    }

    private function loadDemoUsers(ObjectManager $manager): void
    {
        // Particulier demo
        $personal = new User();
        $personal->setEmail('demo@flowfee.com');
        $personal->setFirstName('Sophie');
        $personal->setLastName('Martin');
        $personal->setType(User::TYPE_PERSONAL);
        $personal->setPassword($this->passwordHasher->hashPassword($personal, 'demo1234'));
        $personal->setIsVerified(true);
        $manager->persist($personal);

        // Pro demo
        $pro = new User();
        $pro->setEmail('admin@flowfee.com');
        $pro->setFirstName('Thomas');
        $pro->setLastName('Dupont');
        $pro->setType(User::TYPE_PROFESSIONAL);
        $pro->setRoles(['ROLE_COMPANY_ADMIN']);
        $pro->setPassword($this->passwordHasher->hashPassword($pro, 'admin1234'));
        $pro->setIsVerified(true);
        $manager->persist($pro);
    }
}
