<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ApiEdgeCasesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($this->entityManager);
        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadatas);
        $schemaTool->createSchema($metadatas);
    }

    public function testHealthCheckIsPublicAndReturnsStatus(): void
    {
        $this->client->request('GET', '/health');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('healthy', $data['status']);
    }

    public function testCreateBookingRejectsMalformedJson(): void
    {
        $this->createAndLoginUser();

        $this->client->request('POST', '/api/bookings', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'das ist kein json');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('INVALID_JSON', $data['error']);
    }

    public function testAvailabilityRejectsInvalidDateFormat(): void
    {
        $this->createAndLoginUser();

        $room = new \App\Entity\Room();
        $room->setName('Zimmer');
        $room->setSlug('zimmer-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);
        $this->entityManager->persist($room);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf(
            '/api/rooms/%s/availability?start_date=kein-datum&end_date=2030-01-05',
            $room->getId()->toRfc4122()
        ));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('INVALID_DATE_FORMAT', $data['error']);
    }

    public function testAvailableRoomsRejectsInvalidDateFormat(): void
    {
        $this->createAndLoginUser();

        $this->client->request('GET', '/api/rooms/available?checkIn=nicht-ein-datum&checkOut=2030-01-05');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('INVALID_DATE_FORMAT', $data['error']);
    }

    public function testRegisterEndpointCreatesUserAndReturnsToken(): void
    {
        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'neu@example.com',
            'password' => 'SicheresPasswort123!',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Benutzer erfolgreich registriert.', $data['message']);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'neu@example.com']);
        $this->assertNotNull($user, 'Der User muss persistiert worden sein');
        $this->assertNotSame('SicheresPasswort123!', $user->getPassword(), 'Passwort muss gehasht gespeichert werden');
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $passwordHasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);
        $existing = new User();
        $existing->setEmail('duplikat@example.com');
        $existing->setPassword($passwordHasher->hashPassword($existing, 'password123'));
        $this->entityManager->persist($existing);
        $this->entityManager->flush();

        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'duplikat@example.com',
            'password' => 'SicheresPasswort123!',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testProtectedEndpointRejectsInvalidToken(): void
    {
        $this->client->request('GET', '/api/rooms', [], [], [
            'HTTP_COOKIE' => 'BEARER=ungueltiger-token',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function createAndLoginUser(): void
    {
        $passwordHasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('edgecase@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'edgecase@example.com',
            'password' => 'password123',
        ]));

        foreach ($this->client->getResponse()->headers->all('Set-Cookie') as $header) {
            if (str_starts_with($header, 'BEARER=')) {
                $parts = explode(';', $header);
                [$name, $value] = explode('=', $parts[0], 2);
                $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie(
                    $name,
                    $value,
                    null,
                    '/',
                    'localhost',
                    false,
                    true,
                    false,
                    'lax'
                ));
                break;
            }
        }
    }
}
