<?php

namespace App\Entity;

use App\Repository\RoomRepository;
use Doctrine\ORM\Mapping as ORM;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Table(name: 'rooms')]
#[ORM\Index(columns: ['slug'], name: 'idx_room_slug')]
#[ORM\HasLifecycleCallbacks]
#[OA\Schema(
    description: "Repräsentiert ein Hotelzimmer mit allen relevanten Informationen",
    required: ['name', 'slug', 'capacity', 'basePrice']
)]
class Room
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[OA\Property(
        description: "Eindeutige UUID des Hotelzimmers",
        type: "string",
        format: "uuid",
        example: "a1b2c3d4-e5f6-7890-g1h2-i3j4k5l6m7n8"
    )]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[OA\Property(
        description: "Name des Hotelzimmers (z.B. \"Standard Zimmer\")",
        example: "Standard Zimmer"
    )]
    private string $name;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[OA\Property(
        description: "Eindeutiger Slug für URL-Zwecke",
        example: "standard-zimmer"
    )]
    private string $slug;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 20)]
    #[OA\Property(
        description: "Kapazität des Zimmers (Anzahl der Gäste)",
        example: 2
    )]
    private int $capacity;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    #[OA\Property(
        description: "Basispreis pro Nacht in Euro",
        type: "number",
        format: "float",
        example: 120.00
    )]
    private string $basePrice;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[OA\Property(
        description: "URL zum iCal-Feed von Booking.com für Kalendersynchronisation",
        example: "https://admin.booking.com/ical/feed.a1b2c3d4.ics",
        nullable: true
    )]
    private ?string $icalUrlBooking = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[OA\Property(
        description: "URL zum iCal-Feed von HomeAway für Kalendersynchronisation",
        example: "https://www.homeaway.com/ical/feed.x1y2z3.ics",
        nullable: true
    )]
    private ?string $icalUrlHomeaway = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[OA\Property(
        description: "Zeitpunkt der letzten Synchronisation mit Booking.com",
        type: "string",
        format: "date-time",
        nullable: true
    )]
    private ?\DateTimeInterface $lastSyncBooking = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[OA\Property(
        description: "Zeitpunkt der letzten Synchronisation mit HomeAway",
        type: "string",
        format: "date-time",
        nullable: true
    )]
    private ?\DateTimeInterface $lastSyncHomeaway = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    #[OA\Property(
        description: "Optimistic Locking Counter - wird bei jedem Update erhöht",
        example: 1
    )]
    private int $version = 0;

    #[ORM\Column(type: 'boolean')]
    #[OA\Property(
        description: "Gibt an, ob das Zimmer aktiv ist und gebucht werden kann",
        example: true
    )]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    #[OA\Property(
        description: "Erstellungszeitpunkt des Datensatzes",
        type: "string",
        format: "date-time"
    )]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    #[OA\Property(
        description: "Zeitpunkt der letzten Aktualisierung des Datensatzes",
        type: "string",
        format: "date-time"
    )]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        $this->updatedAt = new \DateTime();

        return $this;
    }
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function ensureSlug(): void
    {
        if (empty($this->slug)) {
            // Generiere einen Slug aus dem Namen, falls er null ist
            $this->slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $this->name)));
        }
    }
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getBasePrice(): string
    {
        return $this->basePrice;
    }

    public function setBasePrice(string $basePrice): static
    {
        $this->basePrice = $basePrice;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getIcalUrlBooking(): ?string
    {
        return $this->icalUrlBooking;
    }

    public function setIcalUrlBooking(?string $icalUrlBooking): static
    {
        $this->icalUrlBooking = $icalUrlBooking;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getIcalUrlHomeaway(): ?string
    {
        return $this->icalUrlHomeaway;
    }

    public function setIcalUrlHomeaway(?string $icalUrlHomeaway): static
    {
        $this->icalUrlHomeaway = $icalUrlHomeaway;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getLastSyncBooking(): ?\DateTimeInterface
    {
        return $this->lastSyncBooking;
    }

    public function setLastSyncBooking(?\DateTimeInterface $lastSyncBooking): static
    {
        $this->lastSyncBooking = $lastSyncBooking;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getLastSyncHomeaway(): ?\DateTimeInterface
    {
        return $this->lastSyncHomeaway;
    }

    public function setLastSyncHomeaway(?\DateTimeInterface $lastSyncHomeaway): static
    {
        $this->lastSyncHomeaway = $lastSyncHomeaway;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}