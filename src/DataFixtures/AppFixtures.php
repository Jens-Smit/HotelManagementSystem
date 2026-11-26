<?php

namespace App\DataFixtures;
use App\Entity\User;
use App\Entity\Category;
use App\Entity\Post;
use App\Entity\Room;
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
        // Beispiel-User
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword($this->hasher->hashPassword($user, 'password123'));
        $manager->persist($user);

        // Beispiel-Kategorie
        $category = new Category();
        $category->setName('Allgemein');
        $manager->persist($category);

        // Beispiel-Post (damit es was zum Abrufen gibt)
        $post = new Post();
        $post->setTitle('Erster Testpost');
        $post->setContent('Inhalt für Testzwecke.');
        $post->setAuthor($user);
        $post->setCategory($category);
        $post->setSlug('erster-testpost');
        $post->setCreatedAt(new \DateTime());
        $manager->persist($post);
        $roomsData = [
            ['name' => 'Standard Doppelzimmer', 'slug' => 'standard-doppelzimmer', 'price' => 85.00, 'capacity' => 2],
            ['name' => 'Luxus Suite', 'slug' => 'luxus-suite', 'price' => 250.00, 'capacity' => 4],
            ['name' => 'Einzelzimmer Business', 'slug' => 'einzelzimmer-business', 'price' => 65.00, 'capacity' => 1],
        ];

        foreach ($roomsData as $data) {
            $room = new Room();
            $room->setName($data['name']);
            $room->setSlug($data['slug']); // WICHTIG: Hier wird der "slug" gesetzt!
            $room->setBasePrice((string) $data['price']);
            $room->setCapacity($data['capacity']);
            $room->setIsActive(true);
            $room->setCreatedAt(new \DateTime());
            $room->setUpdatedAt(new \DateTime());
            
            $manager->persist($room);
        }
        $manager->flush();
    }
}
