<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\AvailabilitySlotRepository;

#[ORM\Entity(repositoryClass: AvailabilitySlotRepository::class)]
#[ORM\Table(name: "availability_slots")]
class AvailabilitySlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer", options: ["unsigned" => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "professor_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?User $professor = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $startAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $endAt;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $isBooked = false;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $locationLabel = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $locationLat = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $locationLng = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToOne(mappedBy: "slot", targetEntity: RendezVous::class, cascade: ["remove"])]
    private ?RendezVous $rendezVous = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // ===== GETTERS & SETTERS =====

    public function getId(): ?int { return $this->id; }

    public function getProfessor(): ?User { return $this->professor; }
    public function setProfessor(?User $professor): self { $this->professor = $professor; return $this; }

    public function getStartAt(): \DateTimeInterface { return $this->startAt; }
    public function setStartAt(\DateTimeInterface $startAt): self { $this->startAt = $startAt; return $this; }

    public function getEndAt(): \DateTimeInterface { return $this->endAt; }
    public function setEndAt(\DateTimeInterface $endAt): self { $this->endAt = $endAt; return $this; }

    public function isBooked(): bool { return $this->isBooked; }
    public function setIsBooked(bool $isBooked): self { $this->isBooked = $isBooked; return $this; }

    public function getLocationLabel(): ?string { return $this->locationLabel; }
    public function setLocationLabel(?string $locationLabel): self { $this->locationLabel = $locationLabel; return $this; }

    public function getLocationLat(): ?float { return $this->locationLat; }
    public function setLocationLat(?float $locationLat): self { $this->locationLat = $locationLat; return $this; }

    public function getLocationLng(): ?float { return $this->locationLng; }
    public function setLocationLng(?float $locationLng): self { $this->locationLng = $locationLng; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getRendezVous(): ?RendezVous { return $this->rendezVous; }
    public function setRendezVous(?RendezVous $rendezVous): self { $this->rendezVous = $rendezVous; return $this; }
}

