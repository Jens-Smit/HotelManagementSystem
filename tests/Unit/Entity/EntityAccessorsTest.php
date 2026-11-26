<?php
declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AvailabilitySnapshot;
use App\Entity\Category;
use App\Entity\Post;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EntityAccessorsTest extends TestCase
{
    public function testRoomAccessors(): void
    {
        $room = new Room();

        $room->setName('Suite');
        $room->setSlug('suite');
        $room->setCapacity(3);
        $room->setBasePrice('250.00');
        $room->setIsActive(true);
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');
        $room->setIcalUrlHomeaway('https://homeaway.example.com/feed.ics');
        $room->setLastSyncBooking(new \DateTime('2026-01-01'));
        $room->setLastSyncHomeaway(new \DateTime('2026-01-02'));

        $this->assertSame('Suite', $room->getName());
        $this->assertSame('suite', $room->getSlug());
        $this->assertSame(3, $room->getCapacity());
        $this->assertSame('250.00', $room->getBasePrice());
        $this->assertTrue($room->isActive());
        $this->assertSame('https://booking.example.com/feed.ics', $room->getIcalUrlBooking());
        $this->assertSame('https://homeaway.example.com/feed.ics', $room->getIcalUrlHomeaway());
        $this->assertSame('2026-01-01', $room->getLastSyncBooking()->format('Y-m-d'));
        $this->assertSame('2026-01-02', $room->getLastSyncHomeaway()->format('Y-m-d'));
    }

    public function testAvailabilitySnapshotAccessors(): void
    {
        $room = new Room();
        $room->setName('Zimmer');
        $room->setSlug('zimmer');
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);

        $snapshot = new AvailabilitySnapshot();
        $snapshot->setRoom($room);
        $snapshot->setDate(new \DateTime('2030-01-15'));
        $snapshot->setIsAvailable(false);
        $snapshot->setSource('booking_com');
        $snapshot->setLastChecked(new \DateTime('2030-01-01'));

        $this->assertSame($room, $snapshot->getRoom());
        $this->assertSame('2030-01-15', $snapshot->getDate()->format('Y-m-d'));
        $this->assertFalse($snapshot->isAvailable());
        $this->assertSame('booking_com', $snapshot->getSource());
        $this->assertSame('2030-01-01', $snapshot->getLastChecked()->format('Y-m-d'));
    }

    public function testUserAccessorsAndRoles(): void
    {
        $user = new User();

        $user->setEmail('gast@example.com');
        $user->setPassword('hash');

        $this->assertSame('gast@example.com', $user->getEmail());
        $this->assertSame('gast@example.com', $user->getUserIdentifier());
        $this->assertSame('hash', $user->getPassword());
        $this->assertContains('ROLE_USER', $user->getRoles());

        $user->setRoles(['ROLE_ADMIN']);
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles(), 'ROLE_USER muss immer erhalten bleiben');

        $this->assertNull($user->getResetToken());
        $user->setResetTokenPlain('plain-token', 3600);
        $this->assertSame(hash('sha256', 'plain-token'), $user->getResetTokenHash());
        $this->assertTrue($user->verifyResetToken('plain-token'));
        $this->assertFalse($user->verifyResetToken('falsch'));
        $user->clearResetToken();
        $this->assertNull($user->getResetTokenHash());
        $this->assertFalse($user->verifyResetToken('plain-token'));

        $user->eraseCredentials();
        $this->addToAssertionCount(1);
    }

    public function testCategoryAccessors(): void
    {
        $parent = new Category();
        $parent->setName('Übernachtung');

        $child = new Category();
        $child->setName('Hotel');
        $child->setParent($parent);

        $this->assertSame('Übernachtung', $parent->getName());
        $this->assertSame($parent, $child->getParent());
        $this->assertNull($parent->getParent());
    }

    public function testPostAccessors(): void
    {
        $user = new User();
        $user->setEmail('author@example.com');
        $user->setPassword('hash');

        $category = new Category();
        $category->setName('News');

        $post = new Post();
        $post->setTitle('Willkommen');
        $post->setContent('Unser erster Beitrag.');
        $post->setSlug('willkommen');
        $post->setAuthor($user);
        $post->setCategory($category);

        $this->assertSame('Willkommen', $post->getTitle());
        $this->assertSame('Unser erster Beitrag.', $post->getContent());
        $this->assertSame('willkommen', $post->getSlug());
        $this->assertSame('author@example.com', $post->getAuthor()->getEmail());
        $this->assertSame('News', $post->getCategory()->getName());
    }

    private function registryWithManager(?ObjectManager $manager)
    {
        $registry = $this->createMock(\Doctrine\Persistence\ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return $registry;
    }
}
