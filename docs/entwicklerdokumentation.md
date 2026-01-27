# Entwicklerdokumentation für das Hotel-Management-System

## Inhaltsverzeichnis
1. [Projektübersicht](#projektübersicht)
2. [Technologie-Stack](#technologie-stack)
3. [Projektstruktur](#projektstruktur)
4. [Entwicklungsumgebung einrichten](#entwicklungsumgebung-einrichten)
5. [Code-Standards und Best Practices](#code-standards-und-best-practices)
6. [Datenbank und Entitäten](#datenbank-und-entitäten)
7. [API-Entwicklung](#api-entwicklung)
8. [Services und Geschäftslogik](#services-und-geschäftslogik)
9. [Tests](#tests)
10. [Deployment](#deployment)
11. [Continuous Integration/Continuous Deployment (CI/CD)](#continuous-integrationcontinuous-deployment-cicd)
12. [Sicherheit](#sicherheit)
13. [Fehlerbehandlung und Logging](#fehlerbehandlung-und-logging)
14. [Performance-Optimierung](#performance-optimierung)
15. [Beitrag zum Projekt](#beitrag-zum-projekt)
16. [Häufig gestellte Fragen (FAQ)](#häufig-gestellte-fragen-faq)

---

## Projektübersicht

Das Hotel-Management-System (PMS) ist eine webbasierte Anwendung zur Verwaltung von Hotelzimmern, Buchungen und Verfügbarkeiten. Das System bietet folgende Hauptfunktionen:

- **Zimmerverwaltung**: Verwaltung aller Hotelzimmer mit Details wie Kapazität, Preis und Verfügbarkeit
- **Buchungsmanagement**: Erstellen, Bearbeiten und Stornieren von Buchungen
- **Kalendersynchronisation**: Synchronisation mit externen Plattformen wie Booking.com und HomeAway
- **Verfügbarkeitsprüfung**: Echtzeit-Prüfung der Zimmerverfügbarkeit
- **Mehrschichtige Überbuchungsschutz**: 5-Schichten-System zur Verhinderung von Überbuchungen
- **API**: RESTful API für die Integration mit externen Systemen

---

## Technologie-Stack

### Backend
- **Framework**: Symfony 7.3 
- **Programmiersprache**: PHP 8.2+
- **Datenbank**: MySQL 8.0+ oder MariaDB 10.4+
- **ORM**: Doctrine ORM
- **API-Dokumentation**: NelmioApiDocBundle (OpenAPI/Swagger)
- **Authentifizierung**: LexikJWTAuthenticationBundle (JWT)
- **Dependency Injection**: Symfony Dependency Injection
- **Caching**: Symfony Cache
- **Logging**: Monolog
- **Testing**: PHPUnit

### Frontend 
- **Template-Engine**: Twig


### Infrastruktur
- **Webserver**: Apache 2.4+ oder Nginx 1.18+
- **Containerisierung**: Docker (optional)
- **CI/CD**: GitHub Actions oder GitLab CI
- **Monitoring**: Sentry, Prometheus, Grafana (optional)

---

## Projektstruktur

```
Hotel-Management-System/
├── bin/                    # Konsolenbefehle
├── config/                 # Konfigurationsdateien
│   ├── packages/           # Bundle-spezifische Konfigurationen
│   ├── routes/             # Routen-Konfigurationen
│   ├── bundles.php         # Bundle-Konfiguration
│   ├── services.yaml       # Service-Konfiguration
│   └── ...
├── docs/                   # Dokumentation
├── migrations/             # Datenbankmigrationen
├── public/                 # Öffentliches Verzeichnis (Webroot)
├── src/
│   ├── Command/            # Konsolenbefehle
│   ├── Controller/         # Controller
│   │   └── Api/            # API-Controller
│   ├── DataFixtures/       # Datenbank-Fixtures
│   ├── DTO/                # Data Transfer Objects
│   │   ├── Request/        # Anfrage-DTOs
│   │   └── Response/       # Antwort-DTOs
│   ├── Entity/             # Entitäten
│   ├── Enum/               # Enumerationen
│   ├── EventListener/      # Event-Listener
│   ├── Repository/         # Repository-Klassen
│   ├── Security/           # Sicherheitskomponenten
│   ├── Service/            # Services und Geschäftslogik
│   └── Kernel.php          # Kernel-Klasse
├── templates/              # Twig-Templates
├── tests/                  # Tests
│   ├── Controller/         # Controller-Tests
│   ├── Fixtures/           # Test-Fixtures
│   ├── Integration/        # Integrations-Tests
│   ├── Service/            # Service-Tests
│   └── Unit/               # Unit-Tests
├── translations/           # Übersetzungen
├── var/                    # Cache, Logs, etc.
├── vendor/                 # Abhängigkeiten
├── .env                    # Umgebungsvariablen
├── .env.example            # Beispiel-Konfiguration
├── .gitignore              # Git-Ignore-Datei
├── composer.json           # Composer-Konfiguration
├── composer.lock           # Composer-Lock-Datei
├── phpunit.xml.dist        # PHPUnit-Konfiguration
└── README.md               # Projekt-README
```

---

## Entwicklungsumgebung einrichten

### Voraussetzungen
- PHP 8.2+
- Composer 2.0+
- MySQL 8.0+ oder MariaDB 10.4+
- Git
- Node.js (optional, für Frontend-Builds)

### Schritte zur Einrichtung

1. **Repository klonen**:
   ```bash
   git clone https://github.com/Jens-Smit/HotelManagementSystem.git Hotel-Management-System
   cd Hotel-Management-System
   ```

2. **Abhängigkeiten installieren**:
   ```bash
   composer install
   npm install  # Falls Frontend-Abhängigkeiten vorhanden
   ```

3. **Umgebungsvariablen konfigurieren**:
   ```bash
   cp .env.example .env
   ```
   - Bearbeiten Sie die `.env`-Datei und konfigurieren Sie die Datenbankverbindung und andere Einstellungen.

4. **Datenbank einrichten**:
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   php bin/console doctrine:fixtures:load  # Optional: Testdaten laden
   ```

5. **JWT-Schlüssel generieren**:
   ```bash
   mkdir -p config/jwt
   openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
   openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
   ```

6. **Entwicklungsserver starten**:
   ```bash
   symfony serve
   ```
   - Die Anwendung ist nun unter `http://localhost:8000` erreichbar.

---

## Code-Standards und Best Practices

### PHP-Coding-Standards
- **PSR-12**: Einhaltung der PSR-12-Coding-Standards
- **Type Declarations**: Verwendung von Typdeklarationen für Parameter und Rückgabewerte
- **DocBlocks**: Verwendung von DocBlocks für Klassen, Methoden und Eigenschaften
- **Dependency Injection**: Verwendung des Symfony Dependency Injection Containers

### Beispiel für eine gut strukturierte Klasse
```php
<?php

namespace App\Service;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service für die Verwaltung von Buchungen.
 */
class BookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Erstellt eine neue Buchung.
     *
     * @param array $bookingData Daten der Buchung
     * @return Booking Die erstellte Buchung
     * @throws \InvalidArgumentException Wenn die Buchungsdaten ungültig sind
     */
    public function createBooking(array $bookingData): Booking
    {
        // Validierung der Eingabedaten
        $this->validateBookingData($bookingData);

        // Geschäftslogik
        $booking = new Booking();
        $booking->setRoom($bookingData['room']);
        $booking->setStartDate($bookingData['start_date']);
        $booking->setEndDate($bookingData['end_date']);
        $booking->setGuestName($bookingData['guest_name']);
        $booking->setGuestEmail($bookingData['guest_email']);
        $booking->setTotalPrice($this->calculateTotalPrice($bookingData));

        // Persistenz
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->logger->info('Buchung erstellt', ['booking_id' => $booking->getId()]);

        return $booking;
    }

    /**
     * Validiert die Buchungsdaten.
     *
     * @param array $bookingData Zu validierende Daten
     * @throws \InvalidArgumentException Wenn die Daten ungültig sind
     */
    private function validateBookingData(array $bookingData): void
    {
        if (empty($bookingData['room'])) {
            throw new \InvalidArgumentException('Zimmer ist erforderlich');
        }

        if (empty($bookingData['start_date']) || empty($bookingData['end_date'])) {
            throw new \InvalidArgumentException('Start- und Enddatum sind erforderlich');
        }

        if ($bookingData['start_date'] >= $bookingData['end_date']) {
            throw new \InvalidArgumentException('Enddatum muss nach dem Startdatum liegen');
        }
    }

    /**
     * Berechnet den Gesamtpreis einer Buchung.
     *
     * @param array $bookingData Buchungsdaten
     * @return string Der Gesamtpreis
     */
    private function calculateTotalPrice(array $bookingData): string
    {
        $nights = $bookingData['start_date']->diff($bookingData['end_date'])->days;
        return bcmul($bookingData['room']->getBasePrice(), (string)$nights, 2);
    }
}
```

### Best Practices
1. **Single Responsibility Principle (SRP)**: Jede Klasse sollte nur eine Verantwortung haben.
2. **Dependency Injection**: Abhängigkeiten sollten injiziert und nicht hartkodiert werden.
3. **Immutable Objects**: Verwenden Sie unveränderliche Objekte, wo möglich.
4. **Separation of Concerns**: Trennen Sie Geschäftslogik von Persistenz und Präsentation.
5. **Defensive Programming**: Validieren Sie alle Eingabedaten.
6. **Fehlerbehandlung**: Behandeln Sie Fehler angemessen und loggen Sie sie.
7. **Performance**: Vermeiden Sie N+1-Abfragen und optimieren Sie Datenbankabfragen.
8. **Sicherheit**: Schützen Sie sich vor gängigen Sicherheitslücken (XSS, CSRF, SQL-Injection, etc.).

---

## Datenbank und Entitäten

### Datenbankschema
Das Datenbankschema besteht aus den folgenden Haupttabellen:

1. **rooms**: Informationen zu Hotelzimmern
2. **bookings**: Buchungen von Gästen
3. **availability_snapshots**: Cache für Verfügbarkeitsinformationen
4. **users**: Benutzerkonten für das System

### Entitäten

#### Room-Entität
```php
#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Table(name: 'rooms')]
class Room
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'integer')]
    private int $capacity;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $basePrice;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $icalUrlBooking = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $icalUrlHomeaway = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastSyncBooking = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastSyncHomeaway = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    // Getter und Setter...
}
```

#### Booking-Entität
```php
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
#[ORM\UniqueConstraint(
    name: 'unique_booking',
    columns: ['room_id', 'start_date', 'end_date', 'status'],
    options: ['where' => '(status != \'cancelled\')']
)]
class Booking
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Room $room;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $endDate;

    #[ORM\Column(type: 'string', length: 200)]
    private string $guestName;

    #[ORM\Column(type: 'string', length: 180)]
    private string $guestEmail;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $guestPhone = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalPrice;

    #[ORM\Column(type: 'string', length: 20, enumType: BookingSource::class)]
    private string $source;

    #[ORM\Column(type: 'string', length: 20, enumType: BookingStatus::class)]
    private string $status;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $bookingHash;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    // Getter und Setter...
}
```

### Enumerationen

#### BookingSource
```php
enum BookingSource: string
{
    case WEBSITE = 'website';
    case BOOKING_COM = 'booking_com';
    case HOMEAWAY = 'homeaway';
    case MANUAL = 'manual';
}
```

#### BookingStatus
```php
enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case BLOCKED = 'blocked';
}
```

### Datenbankmigrationen
- **Erstellen einer Migration**:
  ```bash
  php bin/console make:migration
  ```
- **Ausführen von Migrationen**:
  ```bash
  php bin/console doctrine:migrations:migrate
  ```
- **Status von Migrationen prüfen**:
  ```bash
  php bin/console doctrine:migrations:status
  ```

---

## API-Entwicklung

### Controller
API-Controller sollten dünn sein und die Geschäftslogik an Services delegieren.

#### Beispiel-Controller
```php
<?php

namespace App\Controller\Api;

use App\DTO\Request\CreateBookingRequest;
use App\DTO\Response\BookingResponse;
use App\Service\BookingService;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/bookings')]
class BookingController extends AbstractController
{
    public function __construct(
        private BookingService $bookingService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/bookings",
     *     summary="Erstellt eine neue Buchung",
     *     @OA\RequestBody(
     *         required=true,
     *         @Model(type=CreateBookingRequest::class)
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Buchung erfolgreich erstellt",
     *         @Model(type=BookingResponse::class)
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ungültige Eingabedaten"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Verfügbarkeitskonflikt"
     *     )
     * )
     */
    #[Route('', name: 'api_bookings_create', methods: ['POST'])]
    public function create(CreateBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->createBooking($request->toArray());

        return $this->json(
            $this->serializer->serialize(new BookingResponse($booking), 'json'),
            Response::HTTP_CREATED,
            [],
            ['groups' => ['api']]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/bookings/{id}",
     *     summary="Ruft eine Buchung ab",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Buchung erfolgreich abgerufen",
     *         @Model(type=BookingResponse::class)
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Buchung nicht gefunden"
     *     )
     * )
     */
    #[Route('/{id}', name: 'api_bookings_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $booking = $this->bookingService->getBookingById($id);

        return $this->json(
            $this->serializer->serialize(new BookingResponse($booking), 'json'),
            Response::HTTP_OK,
            [],
            ['groups' => ['api']]
        );
    }
}
```

### Data Transfer Objects (DTOs)
DTOs werden verwendet, um die Eingabe- und Ausgabedaten der API zu strukturieren.

#### Beispiel-DTO (Anfrage)
```php
<?php

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateBookingRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $roomId;

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $startDate;

    #[Assert\NotBlank]
    #[Assert\Date]
    #[Assert\GreaterThan(propertyPath: "startDate")]
    public string $endDate;

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $guestName;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $guestEmail;

    #[Assert\Length(max: 50)]
    public ?string $guestPhone = null;

    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'start_date' => new \DateTime($this->startDate),
            'end_date' => new \DateTime($this->endDate),
            'guest_name' => $this->guestName,
            'guest_email' => $this->guestEmail,
            'guest_phone' => $this->guestPhone,
        ];
    }
}
```

#### Beispiel-DTO (Antwort)
```php
<?php

namespace App\DTO\Response;

use App\Entity\Booking;
use Symfony\Component\Serializer\Annotation\Groups;

class BookingResponse
{
    #[Groups(['api'])]
    public string $id;

    #[Groups(['api'])]
    public RoomResponse $room;

    #[Groups(['api'])]
    public string $startDate;

    #[Groups(['api'])]
    public string $endDate;

    #[Groups(['api'])]
    public string $guestName;

    #[Groups(['api'])]
    public string $guestEmail;

    #[Groups(['api'])]
    public ?string $guestPhone;

    #[Groups(['api'])]
    public string $totalPrice;

    #[Groups(['api'])]
    public string $source;

    #[Groups(['api'])]
    public string $status;

    #[Groups(['api'])]
    public ?string $externalId;

    #[Groups(['api'])]
    public ?string $notes;

    #[Groups(['api'])]
    public string $bookingHash;

    #[Groups(['api'])]
    public string $createdAt;

    #[Groups(['api'])]
    public string $updatedAt;

    public function __construct(Booking $booking)
    {
        $this->id = $booking->getId();
        $this->room = new RoomResponse($booking->getRoom());
        $this->startDate = $booking->getStartDate()->format('Y-m-d');
        $this->endDate = $booking->getEndDate()->format('Y-m-d');
        $this->guestName = $booking->getGuestName();
        $this->guestEmail = $booking->getGuestEmail();
        $this->guestPhone = $booking->getGuestPhone();
        $this->totalPrice = $booking->getTotalPrice();
        $this->source = $booking->getSource();
        $this->status = $booking->getStatus();
        $this->externalId = $booking->getExternalId();
        $this->notes = $booking->getNotes();
        $this->bookingHash = $booking->getBookingHash();
        $this->createdAt = $booking->getCreatedAt()->format(\DateTime::ATOM);
        $this->updatedAt = $booking->getUpdatedAt()->format(\DateTime::ATOM);
    }
}
```

### API-Dokumentation
Die API-Dokumentation wird mit NelmioApiDocBundle generiert und ist unter `/api/doc` erreichbar.

- **Konfiguration** (`config/packages/nelmio_api_doc.yaml`):
  ```yaml
  nelmio_api_doc:
      documentation:
          info:
              title: Hotel-Management-System API
              description: API für die Verwaltung von Hotelzimmern und Buchungen
              version: 1.0.0
          components:
              securitySchemes:
                  Bearer:
                      type: http
                      scheme: bearer
                      bearerFormat: JWT
      areas:
          path_patterns: ['^/api']
  ```

---

## Services und Geschäftslogik

### Service-Architektur
Die Geschäftslogik ist in Services organisiert, die vom Dependency Injection Container verwaltet werden.

#### Beispiel-Service: AvailabilityService
```php
<?php

namespace App\Service;

use App\Entity\Room;
use App\Enum\AvailabilitySource;
use App\Repository\AvailabilitySnapshotRepository;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AvailabilityService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvailabilitySnapshotRepository $availabilitySnapshotRepository,
        private BookingRepository $bookingRepository,
        private ICalSyncService $icalSyncService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Prüft die Verfügbarkeit eines Zimmers für einen bestimmten Zeitraum.
     *
     * @param Room $room Das Zimmer
     * @param \DateTimeInterface $startDate Startdatum
     * @param \DateTimeInterface $endDate Enddatum
     * @return array Verfügbarkeitsinformationen
     */
    public function checkAvailability(Room $room, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $this->logger->info('Verfügbarkeit prüfen', [
            'room_id' => $room->getId(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ]);

        $availability = [
            'available' => true,
            'room_id' => $room->getId(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'conflict_dates' => [],
            'conflict_sources' => [],
            'check_sources' => [],
            'breakdown' => []
        ];

        // Interne Verfügbarkeit prüfen
        $internalAvailability = $this->checkInternalAvailability($room, $startDate, $endDate);
        $availability['check_sources'][] = AvailabilitySource::INTERNAL->value;
        $availability['breakdown'] = array_merge($availability['breakdown'], $internalAvailability['breakdown']);

        if (!$internalAvailability['available']) {
            $availability['available'] = false;
            $availability['conflict_dates'] = array_merge(
                $availability['conflict_dates'],
                $internalAvailability['conflict_dates']
            );
            $availability['conflict_sources'][] = AvailabilitySource::INTERNAL->value;
        }

        // Externe Verfügbarkeit prüfen (Booking.com)
        if ($room->getIcalUrlBooking()) {
            try {
                $bookingComAvailability = $this->icalSyncService->checkExternalAvailability(
                    $room,
                    $startDate,
                    $endDate,
                    AvailabilitySource::BOOKING_COM
                );

                $availability['check_sources'][] = AvailabilitySource::BOOKING_COM->value;
                $availability['breakdown'] = array_merge($availability['breakdown'], $bookingComAvailability['breakdown']);

                if (!$bookingComAvailability['available']) {
                    $availability['available'] = false;
                    $availability['conflict_dates'] = array_merge(
                        $availability['conflict_dates'],
                        $bookingComAvailability['conflict_dates']
                    );
                    $availability['conflict_sources'][] = AvailabilitySource::BOOKING_COM->value;
                }
            } catch (\Exception $e) {
                $this->logger->error('Fehler bei der Prüfung der Booking.com-Verfügbarkeit', [
                    'room_id' => $room->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Externe Verfügbarkeit prüfen (HomeAway)
        if ($room->getIcalUrlHomeaway()) {
            try {
                $homeawayAvailability = $this->icalSyncService->checkExternalAvailability(
                    $room,
                    $startDate,
                    $endDate,
                    AvailabilitySource::HOMEAWAY
                );

                $availability['check_sources'][] = AvailabilitySource::HOMEAWAY->value;
                $availability['breakdown'] = array_merge($availability['breakdown'], $homeawayAvailability['breakdown']);

                if (!$homeawayAvailability['available']) {
                    $availability['available'] = false;
                    $availability['conflict_dates'] = array_merge(
                        $availability['conflict_dates'],
                        $homeawayAvailability['conflict_dates']
                    );
                    $availability['conflict_sources'][] = AvailabilitySource::HOMEAWAY->value;
                }
            } catch (\Exception $e) {
                $this->logger->error('Fehler bei der Prüfung der HomeAway-Verfügbarkeit', [
                    'room_id' => $room->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Gesamtpreis berechnen
        $availability['price_per_night'] = $room->getBasePrice();
        $nights = $startDate->diff($endDate)->days;
        $availability['total_price'] = bcmul($room->getBasePrice(), (string)$nights, 2);

        return $availability;
    }

    /**
     * Prüft die interne Verfügbarkeit eines Zimmers.
     *
     * @param Room $room Das Zimmer
     * @param \DateTimeInterface $startDate Startdatum
     * @param \DateTimeInterface $endDate Enddatum
     * @return array Verfügbarkeitsinformationen
     */
    private function checkInternalAvailability(Room $room, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $availability = [
            'available' => true,
            'conflict_dates' => [],
            'breakdown' => []
        ];

        // Verfügbarkeits-Cache prüfen
        $snapshots = $this->availabilitySnapshotRepository->findForDateRange(
            $room,
            $startDate,
            $endDate,
            AvailabilitySource::INTERNAL
        );

        $date = clone $startDate;
        while ($date < $endDate) {
            $dateStr = $date->format('Y-m-d');
            $snapshot = $snapshots[$dateStr] ?? null;

            if ($snapshot && !$snapshot->isAvailable()) {
                $availability['available'] = false;
                $availability['conflict_dates'][] = $dateStr;
            }

            $availability['breakdown'][$dateStr] = [
                'available' => $snapshot ? $snapshot->isAvailable() : true,
                'price' => $room->getBasePrice(),
                'source' => AvailabilitySource::INTERNAL->value
            ];

            $date->modify('+1 day');
        }

        // Buchungen prüfen (falls kein Cache vorhanden)
        if (count($snapshots) < $startDate->diff($endDate)->days) {
            $conflictingBookings = $this->bookingRepository->findConflictingBookings(
                $room,
                $startDate,
                $endDate
            );

            foreach ($conflictingBookings as $booking) {
                $currentDate = clone $booking->getStartDate();
                while ($currentDate <= $booking->getEndDate()) {
                    $dateStr = $currentDate->format('Y-m-d');
                    if ($currentDate >= $startDate && $currentDate < $endDate) {
                        if (!isset($availability['breakdown'][$dateStr]) || $availability['breakdown'][$dateStr]['available']) {
                            $availability['available'] = false;
                            $availability['conflict_dates'][] = $dateStr;
                            $availability['breakdown'][$dateStr] = [
                                'available' => false,
                                'price' => $room->getBasePrice(),
                                'source' => AvailabilitySource::INTERNAL->value
                            ];
                        }
                    }
                    $currentDate->modify('+1 day');
                }
            }
        }

        return $availability;
    }

    /**
     * Aktualisiert den Verfügbarkeits-Cache für ein Zimmer.
     *
     * @param Room $room Das Zimmer
     */
    public function updateAvailabilityCache(Room $room): void
    {
        $this->logger->info('Verfügbarkeits-Cache aktualisieren', ['room_id' => $room->getId()]);

        // Bestehende Snapshots löschen
        $this->availabilitySnapshotRepository->deleteForRoom($room, AvailabilitySource::INTERNAL);

        // Buchungen für die nächsten 12 Monate abrufen
        $startDate = new \DateTime();
        $endDate = (new \DateTime())->modify('+12 months');

        $bookings = $this->bookingRepository->findForDateRange($room, $startDate, $endDate);

        $date = clone $startDate;
        while ($date <= $endDate) {
            $dateStr = $date->format('Y-m-d');
            $isAvailable = true;

            foreach ($bookings as $booking) {
                if ($date >= $booking->getStartDate() && $date <= $booking->getEndDate()) {
                    $isAvailable = false;
                    break;
                }
            }

            $snapshot = new AvailabilitySnapshot();
            $snapshot->setRoom($room);
            $snapshot->setDate($date);
            $snapshot->setIsAvailable($isAvailable);
            $snapshot->setSource(AvailabilitySource::INTERNAL);
            $snapshot->setLastChecked(new \DateTime());

            $this->entityManager->persist($snapshot);

            $date->modify('+1 day');
        }

        $this->entityManager->flush();
    }
}
```

---

## Tests

### Teststrategie
- **Unit-Tests**: Testen einzelne Klassen und Methoden isoliert
- **Integration-Tests**: Testen die Interaktion zwischen Komponenten
- **End-to-End-Tests**: Testen die gesamte Anwendung von der API bis zur Datenbank
- **Performance-Tests**: Testen die Leistung der Anwendung unter Last

### PHPUnit-Konfiguration
Die PHPUnit-Konfiguration befindet sich in `phpunit.xml.dist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         cacheDirectory=".phpunit.cache"
>
    <php>
        <ini name="error_reporting" value="-1" />
        <server name="APP_ENV" value="test" force="true" />
        <server name="SHELL_VERBOSITY" value="-1" />
        <server name="SYMFONY_PHPUNIT_REMOVE" value="" />
        <server name="SYMFONY_PHPUNIT_VERSION" value="9.5" />
    </php>

    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="Controller">
            <directory>tests/Controller</directory>
        </testsuite>
    </testsuites>

    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">src</directory>
        </whitelist>
    </filter>

    <listeners>
        <listener class="Symfony\Bridge\PhpUnit\SymfonyTestsListener" />
    </listeners>
</phpunit>
```

### Beispiel-Unit-Test
```php
<?php

namespace App\Tests\Unit\Service;

use App\Entity\Booking;
use App\Entity\Room;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BookingServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private BookingService $bookingService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->bookingService = new BookingService(
            $this->entityManager,
            $this->createMock(\App\Repository\BookingRepository::class),
            $this->logger
        );
    }

    public function testCreateBooking(): void
    {
        $room = new Room();
        $room->setBasePrice('100.00');

        $bookingData = [
            'room' => $room,
            'start_date' => new \DateTime('2026-03-15'),
            'end_date' => new \DateTime('2026-03-18'),
            'guest_name' => 'Max Mustermann',
            'guest_email' => 'max@example.com'
        ];

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Booking::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Buchung erstellt', $this->anything());

        $booking = $this->bookingService->createBooking($bookingData);

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals('300.00', $booking->getTotalPrice());
        $this->assertEquals('Max Mustermann', $booking->getGuestName());
        $this->assertEquals('max@example.com', $booking->getGuestEmail());
    }

    public function testCreateBookingWithInvalidData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zimmer ist erforderlich');

        $this->bookingService->createBooking([]);
    }

    public function testCalculateTotalPrice(): void
    {
        $room = new Room();
        $room->setBasePrice('120.00');

        $bookingData = [
            'room' => $room,
            'start_date' => new \DateTime('2026-03-15'),
            'end_date' => new \DateTime('2026-03-18'),
            'guest_name' => 'Max Mustermann',
            'guest_email' => 'max@example.com'
        ];

        $reflection = new \ReflectionClass($this->bookingService);
        $method = $reflection->getMethod('calculateTotalPrice');
        $method->setAccessible(true);

        $totalPrice = $method->invoke($this->bookingService, $bookingData);

        $this->assertEquals('360.00', $totalPrice);
    }
}
```

### Beispiel-Integrationstest
```php
<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Room;
use App\Repository\RoomRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RoomControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::$container->get('doctrine')->getManager();
    }

    public function testGetRooms(): void
    {
        $this->client->request('GET', '/api/rooms');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('meta', $responseData);
        $this->assertIsArray($responseData['data']);
    }

    public function testCreateRoom(): void
    {
        $this->client->request('POST', '/api/rooms', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getValidToken()
        ], json_encode([
            'name' => 'Test Zimmer',
            'slug' => 'test-zimmer',
            'capacity' => 2,
            'base_price' => '150.00',
            'is_active' => true
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('room', $responseData);
        $this->assertEquals('Test Zimmer', $responseData['room']['name']);
    }

    public function testGetRoomNotFound(): void
    {
        $this->client->request('GET', '/api/rooms/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('RESOURCE_NOT_FOUND', $responseData['error']);
    }

    private function getValidToken(): string
    {
        // Hier sollte ein gültiges JWT-Token für Tests generiert werden
        // Dies ist ein Platzhalter - in der Praxis sollte ein Test-Benutzer verwendet werden
        return 'valid.jwt.token.here';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Vermeiden von Speicherlecks
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
```

---

## Deployment

### Produktionsumgebung vorbereiten
1. **Server vorbereiten**:
   - Installieren Sie alle erforderlichen Pakete (PHP, MySQL, Webserver, etc.)
   - Konfigurieren Sie die Firewall
   - Richten Sie SSL-Zertifikate ein (z. B. mit Let's Encrypt)

2. **Anwendung deployen**:
   ```bash
   git clone https://github.com/Jens-Smit/HotelManagementSystem.git /var/www/hotel-management-system
   cd /var/www/hotel-management-system
   composer install --optimize-autoloader --no-dev
   npm install --production
   npm run build
   ```

3. **Umgebungsvariablen konfigurieren**:
   ```bash
   cp .env.example .env
   ```
   - Bearbeiten Sie die `.env`-Datei und konfigurieren Sie die Produktionsumgebung.

4. **Datenbank einrichten**:
   ```bash
   php bin/console doctrine:database:create --if-not-exists
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

5. **Cache leeren und aufwärmen**:
   ```bash
   php bin/console cache:clear --env=prod
   php bin/console cache:warmup --env=prod
   ```

6. **Dateiberechtigungen setzen**:
   ```bash
   chown -R www-data:www-data /var/www/hotel-management-system/var
   chmod -R 775 /var/www/hotel-management-system/var
   ```

7. **Cron-Jobs einrichten**:
   ```bash
   crontab -e
   ```
   - Fügen Sie die folgende Zeile hinzu:
     ```
     */15 * * * * cd /var/www/hotel-management-system && php bin/console app:sync-calendars >> /var/log/pms-sync.log 2>&1
     ```

8. **Webserver konfigurieren**:
   - **Apache**:
     ```apache
     <VirtualHost *:80>
         ServerName ihre-domain.de
         ServerAlias www.ihre-domain.de
         DocumentRoot /var/www/hotel-management-system/public

         <Directory /var/www/hotel-management-system/public>
             AllowOverride All
             Order Allow,Deny
             Allow from All
             FallbackResource /index.php
         </Directory>

         ErrorLog ${APACHE_LOG_DIR}/error.log
         CustomLog ${APACHE_LOG_DIR}/access.log combined
     </VirtualHost>
     ```
   - **Nginx**:
     ```nginx
     server {
         listen 80;
         server_name ihre-domain.de www.ihre-domain.de;
         root /var/www/hotel-management-system/public;

         location / {
             try_files $uri /index.php$is_args$args;
         }

         location ~ ^/index\.php(/|$) {
             fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
             fastcgi_split_path_info ^(.+\.php)(/.*)$;
             include fastcgi_params;
             fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
             fastcgi_param DOCUMENT_ROOT $realpath_root;
             internal;
         }

         location ~ \.php$ {
             return 404;
         }

         error_log /var/log/nginx/hotel-management-system_error.log;
         access_log /var/log/nginx/hotel-management-system_access.log;
     }
     ```

9. **HTTPS einrichten**:
   - Installieren Sie Certbot:
     ```bash
     sudo apt-get install certbot python3-certbot-nginx
     ```
   - Zertifikat erstellen:
     ```bash
     sudo certbot --nginx -d ihre-domain.de -d www.ihre-domain.de
     ```

10. **Anwendung neu starten**:
    ```bash
    sudo systemctl restart apache2  # Für Apache
    sudo systemctl restart nginx    # Für Nginx
    sudo systemctl restart php8.1-fpm
    ```

---

## Continuous Integration/Continuous Deployment (CI/CD)

### GitHub Actions
Die CI/CD-Pipeline ist in `.github/workflows/ci.yml` konfiguriert:

```yaml
name: CI

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  tests:
    name: PHP ${{ matrix.php }} Tests
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.1', '8.2']

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: hotel_management_system_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql
          coverage: none

      - name: Get composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache composer dependencies
        uses: actions/cache@v3
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Wait for MySQL
        run: |
          while ! mysqladmin ping --host=127.0.0.1 --password=root --silent; do
            sleep 1
          done

      - name: Run PHPUnit tests
        env:
          DATABASE_URL: mysql://root:root@127.0.0.1:3306/hotel_management_system_test
        run: php bin/phpunit

      - name: Run PHPStan
        run: vendor/bin/phpstan analyse

      - name: Run PHP-CS-Fixer
        run: vendor/bin/php-cs-fixer fix --dry-run --diff
```

### Deployment-Pipeline
Die Deployment-Pipeline ist in `.github/workflows/deploy.yml` konfiguriert:

```yaml
name: Deploy to Production

on:
  push:
    tags:
      - 'v*'

jobs:
  deploy:
    name: Deploy
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql

      - name: Install dependencies
        run: composer install --prefer-dist --no-dev --optimize-autoloader

      - name: Build assets
        run: |
          npm install
          npm run build

      - name: Deploy to production
        uses: appleboy/scp-action@master
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          source: "."
          target: "/var/www/hotel-management-system"

      - name: Run post-deploy commands
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/hotel-management-system
            php bin/console doctrine:migrations:migrate --no-interaction
            php bin/console cache:clear --env=prod
            php bin/console cache:warmup --env=prod
            sudo systemctl restart php8.1-fpm
            sudo systemctl restart nginx
```

---

## Sicherheit

### Sicherheitsmaßnahmen
1. **Authentifizierung**:
   - JWT-Tokens mit kurzer Gültigkeitsdauer (1 Stunde)
   - Refresh-Tokens für die Verlängerung der Sitzung
   - Passwort-Hashing mit Argon2

2. **Autorisierung**:
   - Rollenbasierte Zugriffskontrolle (RBAC)
   - Symfony Security-Komponenten

3. **Datenbank**:
   - Prepared Statements zur Verhinderung von SQL-Injection
   - Verschlüsselung sensibler Daten

4. **API-Sicherheit**:
   - Rate Limiting
   - CORS-Konfiguration
   - CSRF-Schutz für Formulare

5. **Server-Sicherheit**:
   - HTTPS mit TLS 1.2+
   - Sicherheits-Header (CSP, HSTS, etc.)
   - Regelmäßige Sicherheitsupdates

### Sicherheits-Header
Die Sicherheits-Header sind in `config/packages/nelmio_security.yaml` konfiguriert:

```yaml
nelmio_security:
    # Content Security Policy
    csp:
        enforce:
            default-src:
                - 'self'
            script-src:
                - 'self'
                - 'unsafe-inline'
                - 'unsafe-eval'
                - https://cdn.jsdelivr.net
            style-src:
                - 'self'
                - 'unsafe-inline'
                - https://cdn.jsdelivr.net
            img-src:
                - 'self'
                - data:
            font-src:
                - 'self'
            connect-src:
                - 'self'
            frame-src:
                - 'self'
            object-src:
                - 'none'
            upgrade-insecure-requests: true

    # HTTP Strict Transport Security
    hsts:
        enabled: true
        max_age: 31536000
        include_subdomains: true
        preload: true

    # Clickjacking-Schutz
    clickjacking:
        paths:
            '^/.*': DENY

    # XSS-Schutz
    xss_protection:
        enabled: true
        mode_block: true

    # Referrer-Policy
    referrer_policy:
        enabled: true
        policies:
            - 'no-referrer-when-downgrade'
            - 'strict-origin-when-cross-origin'

    # Feature-Policy
    feature_policy:
        enabled: true
        features:
            geolocation: []
            midi: []
            notifications: []
            push: []
            sync-xhr: []
            microphone: []
            camera: []
            magnetometer: []
            gyroscope: []
            speaker: []
            vibrate: []
            fullscreen:
                - 'self'
            payment: []
```

### Passwort-Sicherheit
Die Passwort-Sicherheit wird durch den `PasswordService` gewährleistet:

```php
<?php

namespace App\Service;

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class PasswordService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Hashes a plain password.
     *
     * @param string $plainPassword The plain password
     * @param PasswordAuthenticatedUserInterface $user The user
     * @return string The hashed password
     */
    public function hashPassword(string $plainPassword, PasswordAuthenticatedUserInterface $user): string
    {
        return $this->passwordHasher->hashPassword($user, $plainPassword);
    }

    /**
     * Verifies a plain password against a hashed password.
     *
     * @param string $plainPassword The plain password
     * @param string $hashedPassword The hashed password
     * @param PasswordAuthenticatedUserInterface $user The user
     * @return bool True if the password is valid, false otherwise
     */
    public function verifyPassword(string $plainPassword, string $hashedPassword, PasswordAuthenticatedUserInterface $user): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }

    /**
     * Checks if a password needs to be rehashed.
     *
     * @param string $hashedPassword The hashed password
     * @return bool True if the password needs to be rehashed, false otherwise
     */
    public function needsRehash(string $hashedPassword): bool
    {
        return $this->passwordHasher->needsRehash($hashedPassword);
    }
}
```

---

## Fehlerbehandlung und Logging

### Fehlerbehandlung
Die Fehlerbehandlung erfolgt zentral über den `ExceptionListener`:

```php
<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Nur JSON-Antworten für API-Anfragen
        if (str_starts_with($request->getPathInfo(), '/api')) {
            $response = $this->createApiResponse($exception);
            $event->setResponse($response);
        }

        // Fehler loggen
        $this->logException($exception);
    }

    private function createApiResponse(\Throwable $exception): JsonResponse
    {
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        $errorCode = $this->getErrorCode($exception);
        $message = $this->getErrorMessage($exception, $statusCode);

        $response = [
            'success' => false,
            'error' => $errorCode,
            'message' => $message,
        ];

        // Details nur in der Entwicklungsumgebung anzeigen
        if ($_ENV['APP_ENV'] === 'dev') {
            $response['details'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return new JsonResponse($response, $statusCode);
    }

    private function getErrorCode(\Throwable $exception): string
    {
        if ($exception instanceof HttpExceptionInterface) {
            return match ($exception->getStatusCode()) {
                401 => 'AUTHENTICATION_FAILED',
                403 => 'ACCESS_DENIED',
                404 => 'RESOURCE_NOT_FOUND',
                409 => 'CONFLICT',
                429 => 'RATE_LIMIT_EXCEEDED',
                default => 'API_ERROR',
            };
        }

        return 'INTERNAL_SERVER_ERROR';
    }

    private function getErrorMessage(\Throwable $exception, int $statusCode): string
    {
        if ($_ENV['APP_ENV'] === 'prod') {
            return match ($statusCode) {
                401 => 'Authentifizierung fehlgeschlagen',
                403 => 'Zugriff verweigert',
                404 => 'Ressource nicht gefunden',
                409 => 'Konflikt mit bestehender Ressource',
                429 => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.',
                500 => 'Ein interner Serverfehler ist aufgetreten',
                default => 'Ein Fehler ist aufgetreten',
            };
        }

        return $exception->getMessage();
    }

    private function logException(\Throwable $exception): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($exception instanceof HttpExceptionInterface) {
            $this->logger->error($exception->getMessage(), $context);
        } else {
            $this->logger->critical($exception->getMessage(), $context);
        }
    }
}
```

### Logging
Das Logging wird mit Monolog konfiguriert (`config/packages/monolog.yaml`):

```yaml
monolog:
    handlers:
        main:
            type: fingers_crossed
            action_level: error
            handler: nested
            excluded_http_codes: [404, 405]
            channels: ["!event"]
        nested:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            level: debug
        console:
            type: console
            process_psr_3_messages: false
            channels: ["!event", "!doctrine"]
        deprecation:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.deprecations.log"
        deprecation_filter:
            type: filter
            handler: deprecation
            max_level: info
            channels: ["php"]
        # JSON-Logging für die Produktion
        json:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.json.log"
            level: info
            formatter: monolog.formatter.json
            channels: ["!event", "!doctrine"]
```

### Beispiel-Log-Eintrag
```json
{
  "timestamp": "2026-01-27T14:00:00+01:00",
  "level": "error",
  "channel": "request",
  "context": {
    "exception": "Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException",
    "message": "No route found for \"GET /api/nonexistent\"",
    "file": "/var/www/hotel-management-system/vendor/symfony/http-kernel/EventListener/RouterListener.php",
    "line": 136,
    "trace": "..."
  },
  "extra": {
    "request_id": "a1b2c3d4e5f6g7h8",
    "ip": "192.168.1.1",
    "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
    "url": "/api/nonexistent"
  }
}
```

---

## Performance-Optimierung

### Datenbankoptimierung
1. **Indizes**:
   - Stellen Sie sicher, dass alle häufig abgefragten Felder indiziert sind.
   - Überprüfen Sie die Indizes regelmäßig mit `EXPLAIN`.

2. **Abfrageoptimierung**:
   - Vermeiden Sie N+1-Abfragen mit `fetch="EAGER"` oder manuellen Joins.
   - Verwenden Sie `SELECT` nur für die benötigten Felder.
   - Begrenzen Sie die Ergebnisse mit `LIMIT`.

3. **Caching**:
   - Verwenden Sie den Symfony Cache für häufig abgerufene Daten.
   - Implementieren Sie einen Verfügbarkeits-Cache (wie im `AvailabilityService`).

4. **Datenbankverbindung**:
   - Verwenden Sie Connection Pooling.
   - Optimieren Sie die `pdo_mysql`-Einstellungen in der `php.ini`.

### Code-Optimierung
1. **Lazy Loading**:
   - Verwenden Sie Lazy Loading für Abhängigkeiten, die nicht immer benötigt werden.

2. **Autowiring optimieren**:
   - Deaktivieren Sie Autowiring für Klassen, die nicht als Services verwendet werden.

3. **Komponenten optimieren**:
   - Vermeiden Sie unnötige Abhängigkeiten.
   - Verwenden Sie leichte Alternativen, wo möglich.

4. **OPcache**:
   - Aktivieren Sie OPcache in der Produktion.
   - Konfigurieren Sie OPcache in der `php.ini`:
     ```ini
     opcache.enable=1
     opcache.enable_cli=1
     opcache.memory_consumption=256
     opcache.interned_strings_buffer=16
     opcache.max_accelerated_files=10000
     opcache.revalidate_freq=60
     opcache.fast_shutdown=1
     ```

### Beispiel: Optimierte Abfrage
```php
// Vorher: N+1-Abfrage
$rooms = $roomRepository->findAll();
foreach ($rooms as $room) {
    $bookings = $bookingRepository->findBy(['room' => $room]);
    // ...
}

// Nachher: Optimierte Abfrage mit Join
$rooms = $roomRepository->createQueryBuilder('r')
    ->leftJoin('r.bookings', 'b')
    ->addSelect('b')
    ->getQuery()
    ->getResult();
```

### Beispiel: Caching
```php
use Symfony\Contracts\Cache\CacheInterface;

class RoomService
{
    public function __construct(
        private CacheInterface $cache,
        private RoomRepository $roomRepository
    ) {
    }

    public function getActiveRooms(): array
    {
        return $this->cache->get('active_rooms', function () {
            return $this->roomRepository->findBy(['isActive' => true]);
        });
    }
}
```

---

## Beitrag zum Projekt

### Wie Sie beitragen können
1. **Fehler melden**: Erstellen Sie ein Issue im GitHub-Repository.
2. **Feature-Anfragen**: Erstellen Sie ein Issue mit dem Label "enhancement".
3. **Pull Requests**: Erstellen Sie einen Pull Request mit Ihren Änderungen.

### Entwicklungsworkflow
1. **Forken Sie das Repository** auf GitHub.
2. **Klonen Sie Ihr Fork**:
   ```bash
   git clone https://github.com/Jens-Smit/HotelManagementSystem.git
   cd HotelManagementSystem
   ```
3. **Erstellen Sie einen Branch** für Ihre Änderungen:
   ```bash
   git checkout -b feature/ihre-funktion
   ```
4. **Nehmen Sie Ihre Änderungen vor** und committen Sie sie:
   ```bash
   git add .
   git commit -m "Beschreibung Ihrer Änderungen"
   ```
5. **Pushen Sie Ihre Änderungen** zu Ihrem Fork:
   ```bash
   git push origin feature/ihre-funktion
   ```
6. **Erstellen Sie einen Pull Request** auf GitHub.

### Code-Review-Richtlinien
1. **Code-Stil**: Halten Sie sich an die PSR-12-Standards.
2. **Tests**: Fügen Sie Tests für neue Funktionen hinzu.
3. **Dokumentation**: Aktualisieren Sie die Dokumentation bei Bedarf.
4. **Commit-Nachrichten**: Verwenden Sie aussagekräftige Commit-Nachrichten.

### Beispiel-Commit-Nachricht
```
feat(booking): Add cancellation reason field

- Added a new 'cancellation_reason' field to the Booking entity
- Updated the BookingController to handle the new field
- Added validation for the cancellation reason
- Updated the API documentation
```

---

## Häufig gestellte Fragen (FAQ)

### Wie füge ich eine neue Entität hinzu?
1. Erstellen Sie die Entitätsklasse in `src/Entity/`.
2. Erstellen Sie das Repository in `src/Repository/`.
3. Erstellen Sie eine Migration:
   ```bash
   php bin/console make:migration
   ```
4. Führen Sie die Migration aus:
   ```bash
   php bin/console doctrine:migrations:migrate
   ```

### Wie füge ich einen neuen API-Endpunkt hinzu?
1. Erstellen Sie einen neuen Controller in `src/Controller/Api/`.
2. Definieren Sie die Route mit Annotationen.
3. Erstellen Sie die erforderlichen DTOs in `src/DTO/Request/` und `src/DTO/Response/`.
4. Implementieren Sie die Geschäftslogik in einem Service.
5. Dokumentieren Sie den Endpunkt mit NelmioApiDocBundle.

### Wie führe ich Tests aus?
- **Alle Tests ausführen**:
  ```bash
  php bin/phpunit
  ```
- **Unit-Tests ausführen**:
  ```bash
  php bin/phpunit --testsuite Unit
  ```
- **Integration-Tests ausführen**:
  ```bash
  php bin/phpunit --testsuite Integration
  ```
- **Einzelnen Test ausführen**:
  ```bash
  php bin/phpunit tests/Unit/Service/BookingServiceTest.php
  ```

### Wie debugge ich die Anwendung?
1. **Symfony Profiler**: Verwenden Sie den Symfony Profiler für detaillierte Informationen zu Anfragen.
2. **Xdebug**: Konfigurieren Sie Xdebug für Schritt-für-Schritt-Debugging.
3. **Logging**: Überprüfen Sie die Log-Dateien in `var/log/`.
4. **Dump und Die**: Verwenden Sie `dump()` und `dd()` für schnelles Debugging.

### Wie optimiere ich Datenbankabfragen?
1. **EXPLAIN verwenden**: Analysieren Sie Abfragen mit `EXPLAIN`.
2. **Indizes hinzufügen**: Stellen Sie sicher, dass häufig abgefragte Felder indiziert sind.
3. **N+1-Abfragen vermeiden**: Verwenden Sie Joins oder `fetch="EAGER"`.
4. **Caching verwenden**: Cachen Sie häufig abgerufene Daten.

### Wie aktualisiere ich die Abhängigkeiten?
1. **Composer-Abhängigkeiten aktualisieren**:
   ```bash
   composer update
   ```
2. **NPM-Abhängigkeiten aktualisieren**:
   ```bash
   npm update
   ```
3. **Überprüfen Sie die Änderungen** und testen Sie die Anwendung gründlich.

### Wie konfiguriere ich die Anwendung für die Produktion?
1. Setzen Sie `APP_ENV=prod` in der `.env`-Datei.
2. Setzen Sie `APP_DEBUG=0` in der `.env`-Datei.
3. Installieren Sie die Abhängigkeiten ohne Entwicklungsabhängigkeiten:
   ```bash
   composer install --optimize-autoloader --no-dev
   ```
4. Bauen Sie die Assets:
   ```bash
   npm run build
   ```
5. Leeren und wärmen Sie den Cache:
   ```bash
   php bin/console cache:clear --env=prod
   php bin/console cache:warmup --env=prod
   ```

### Wie richte ich die Kalendersynchronisation ein?
1. Fügen Sie die iCal-URLs für Booking.com und HomeAway zu den Zimmern hinzu.
2. Richten Sie den Cron-Job ein:
   ```bash
   crontab -e
   ```
   - Fügen Sie die folgende Zeile hinzu:
     ```
     */15 * * * * cd /pfad/zur/anwendung && php bin/console app:sync-calendars >> /var/log/pms-sync.log 2>&1
     ```
3. Testen Sie die Synchronisation manuell:
   ```bash
   php bin/console app:sync-calendars
   ```

### Wie füge ich eine neue Sprache hinzu?
1. Erstellen Sie die Übersetzungsdateien in `translations/`:
   ```
   messages.de.yaml
   messages.en.yaml
   ```
2. Aktualisieren Sie die Konfiguration in `config/packages/translation.yaml`:
   ```yaml
   framework:
       default_locale: de
       translator:
           default_path: '%kernel.project_dir%/translations'
           fallbacks:
               - de
   ```
3. Verwenden Sie die Übersetzungen in Twig-Templates:
   ```twig
   {{ 'welcome.message'|trans }}
   ```
4. Oder in PHP-Code:
   ```php
   $translator->trans('welcome.message');
   ```

---