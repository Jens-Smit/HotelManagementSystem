<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class HealthAndUserRepositoryTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
    }

    public function testHealthReportsDatabaseDisconnect(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('executeQuery')->willThrowException(new \RuntimeException('server has gone away'));

        static::getContainer()->set('doctrine.dbal.default_connection', $connection);

        $this->client->request('GET', '/health');

        $this->assertResponseStatusCodeSame(503);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('unhealthy', $data['status']);
        $this->assertSame('disconnected', $data['database']);
    }

    public function testUserRepositoryUpgradePassword(): void
    {
        $entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadatas = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadatas);
        $schemaTool->createSchema($metadatas);

        $user = new User();
        $user->setEmail('upgrade@example.com');
        $user->setPassword('old-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        $entityManager->clear();

        /** @var UserRepository $repository */
        $repository = $entityManager->getRepository(User::class);
        $loaded = $repository->findOneBy(['email' => 'upgrade@example.com']);

        $repository->upgradePassword($loaded, 'neuer-hash');

        $entityManager->clear();
        $reloaded = $repository->findOneBy(['email' => 'upgrade@example.com']);
        $this->assertSame('neuer-hash', $reloaded->getPassword());
    }

    public function testUserRepositoryRejectsForeignUserObjects(): void
    {
        $entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadatas = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadatas);
        $schemaTool->createSchema($metadatas);

        $foreign = new class implements \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface {
            public function getPassword(): ?string { return 'x'; }
        };

        $repository = $entityManager->getRepository(User::class);

        $this->expectException(UnsupportedUserException::class);

        $repository->upgradePassword($foreign, 'hash');
    }

    protected function tearDown(): void
    {
        $this->client->getContainer()->get('doctrine')->getManager()->clear();
        self::ensureKernelShutdown();
        parent::tearDown();
    }
}
