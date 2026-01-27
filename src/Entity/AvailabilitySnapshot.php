<?php

namespace App\Entity;

use App\Enum\AvailabilitySource;
use App\Repository\AvailabilitySnapshotRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvailabilitySnapshotRepository::class)]
#[ORM\Table(name: 'availability_snapshots')]
#[ORM\UniqueConstraint(name: 'unique_availability', columns: ['room_id', 'date', 'source'])]
class AvailabilitySnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    private Room $room;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable;

    #[ORM\Column(type: 'string', length: 20, enumType: AvailabilitySource::class)]
    #[Assert\NotBlank]
    private string $source;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $lastChecked;

    public function __construct()
    {
        $this->lastChecked = new \DateTime();
    }

    public function getId(): ?int
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

        return $this;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getLastChecked(): \DateTimeInterface
    {
        return $this->lastChecked;
    }

    public function setLastChecked(\DateTimeInterface $lastChecked): static
    {
        $this->lastChecked = $lastChecked;

        return $this;
    }
}