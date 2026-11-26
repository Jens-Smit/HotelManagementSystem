<?php

namespace App\Entity;

use App\Enum\BookingStatus;
use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
#[ORM\UniqueConstraint(name: 'unique_booking', columns: ['room_id', 'start_date', 'end_date', 'status'])]
#[ORM\Index(name: 'idx_booking_hash', columns: ['booking_hash'])]
#[ORM\Index(columns: ['room_id', 'start_date', 'end_date'], name: 'idx_booking_dates')]
#[OA\Schema(
    description: "Repräsentiert eine Hotelbuchung mit allen relevanten Informationen",
    required: ['room', 'startDate', 'endDate', 'guestName', 'guestEmail', 'totalPrice', 'source', 'status']
)]
class Booking
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[OA\Property(
        description: "Eindeutige UUID der Buchung",
        type: "string",
        format: "uuid",
        example: "e5f6g7h8-i9j0-k1l2-m3n4-o5p6q7r8s9t0"
    )]
    #[Groups(['api'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    #[OA\Property(
        description: "Referenz zum gebuchten Hotelzimmer",
        ref: new Model(type: Room::class, groups: ["api"])
    )]
    #[Groups(['api'])]
    private Room $room;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    #[OA\Property(
        description: "Check-In Datum der Buchung",
        type: "string",
        format: "date",
        example: "2026-03-15"
    )]
    #[Groups(['api'])]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    #[Assert\GreaterThan(propertyPath: 'startDate')]
    #[OA\Property(
        description: "Check-Out Datum der Buchung",
        type: "string",
        format: "date",
        example: "2026-03-20"
    )]
    #[Groups(['api'])]
    private \DateTimeInterface $endDate;

    #[ORM\Column(type: 'string', length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[OA\Property(
        description: "Name des Gastes",
        example: "Max Mustermann"
    )]
    #[Groups(['api'])]
    private string $guestName;

    #[ORM\Column(type: 'string', length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[OA\Property(
        description: "E-Mail-Adresse des Gastes",
        example: "max.mustermann@example.com"
    )]
    #[Groups(['api'])]
    private string $guestEmail;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[OA\Property(
        description: "Telefonnummer des Gastes",
        example: "+49 123 456789",
        nullable: true
    )]
    #[Groups(['api'])]
    private ?string $guestPhone = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    #[OA\Property(
        description: "Gesamtpreis der Buchung in Euro",
        type: "number",
        format: "float",
        example: 600.00
    )]
    #[Groups(['api'])]
    private string $totalPrice;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['website', 'booking_com', 'homeaway', 'manual'])]
    #[OA\Property(
        description: "Quelle der Buchung",
        enum: ['website', 'booking_com', 'homeaway', 'manual'],
        example: "website"
    )]
    #[Groups(['api'])]
    private string $source;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\NotBlank]
    #[OA\Property(
        description: "Status der Buchung",
        enum: ['pending', 'confirmed', 'cancelled', 'blocked'],
        example: "confirmed"
    )]
    #[Groups(['api'])]
    private string $status;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[OA\Property(
        description: "Externe ID von Buchungsplattformen (z.B. Booking.com Referenz)",
        example: "BKG-12345678",
        nullable: true
    )]
    #[Groups(['api'])]
    private ?string $externalId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[OA\Property(
        description: "Zusätzliche Notizen zur Buchung",
        example: "Frühstück inklusive, Allergikerzimmer",
        nullable: true
    )]
    #[Groups(['api'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[OA\Property(
        description: "Eindeutiger Hash zur Identifikation der Buchung (für öffentliche Links)",
        example: "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0"
    )]
    #[Groups(['api'])]
    private string $bookingHash;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    #[OA\Property(
        description: "Optimistic Locking Counter - wird bei jedem Update erhöht",
        example: 1
    )]
    #[Groups(['api'])]
    private int $version = 0;

    #[ORM\Column(type: 'datetime')]
    #[OA\Property(
        description: "Erstellungszeitpunkt der Buchung",
        type: "string",
        format: "date-time"
    )]
    #[Groups(['api'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    #[OA\Property(
        description: "Zeitpunkt der letzten Aktualisierung der Buchung",
        type: "string",
        format: "date-time"
    )]
    #[Groups(['api'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->status = BookingStatus::PENDING->value;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getRoom(): Room
    {
        return $this->room;
    }

    public function setRoom(Room $room): static
    {
        $this->room = $room;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getGuestName(): string
    {
        return $this->guestName;
    }

    public function setGuestName(string $guestName): static
    {
        $this->guestName = $guestName;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getGuestEmail(): string
    {
        return $this->guestEmail;
    }

    public function setGuestEmail(string $guestEmail): static
    {
        $this->guestEmail = $guestEmail;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getGuestPhone(): ?string
    {
        return $this->guestPhone;
    }

    public function setGuestPhone(?string $guestPhone): static
    {
        $this->guestPhone = $guestPhone;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getBookingHash(): string
    {
        return $this->bookingHash;
    }

    public function setBookingHash(string $bookingHash): static
    {
        $this->bookingHash = $bookingHash;
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