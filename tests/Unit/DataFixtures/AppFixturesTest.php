<?php
declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\Category;
use App\Entity\Post;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixturesTest extends TestCase
{
    public function testLoadPersistsDemoDataset(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed-password');

        $persisted = [];
        $manager = $this->createMock(ObjectManager::class);
        $manager->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $manager->expects($this->once())->method('flush');

        (new AppFixtures($hasher))->load($manager);

        $this->assertCount(6, $persisted, '1 User + 1 Kategorie + 1 Post + 3 Zimmer');

        $users = array_values(array_filter($persisted, fn ($e) => $e instanceof User));
        $this->assertCount(1, $users);
        $this->assertSame('test@example.com', $users[0]->getEmail());
        $this->assertSame('hashed-password', $users[0]->getPassword());

        $rooms = array_values(array_filter($persisted, fn ($e) => $e instanceof Room));
        $this->assertCount(3, $rooms);
        foreach ($rooms as $room) {
            $this->assertTrue($room->isActive(), 'Demo-Zimmer müssen aktiv sein');
            $this->assertNotSame('', $room->getSlug());
        }

        $posts = array_values(array_filter($persisted, fn ($e) => $e instanceof Post));
        $this->assertCount(1, $posts);
        $this->assertSame($users[0], $posts[0]->getAuthor());

        $categories = array_values(array_filter($persisted, fn ($e) => $e instanceof Category));
        $this->assertCount(1, $categories);
    }
}
