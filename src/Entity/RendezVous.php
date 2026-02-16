<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\RendezVousRepository;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
#[ORM\Table(name: "rendez_vous")]
class RendezVous
{
    public const STATUS_EN_ATTENTE = 'en_attente';
    public const STATUS_CONFIRME = 'confirmé';
    public const STATUS_REJETE = 'rejeté';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "en_attente"])]
    private string $statut = self::STATUS_EN_ATTENTE;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "string", length: 64, nullable: true)]
    private ?string $ownerToken = null;

    #[ORM\OneToOne(inversedBy: "rendezVous", targetEntity: AvailabilitySlot::class)]
    #[ORM\JoinColumn(name: "slot_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE", unique: true)]
    private ?AvailabilitySlot $slot = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // ===== GETTERS & SETTERS =====

    public function getId(): ?int { return $this->id; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getOwnerToken(): ?string { return $this->ownerToken; }
    public function setOwnerToken(?string $ownerToken): self { $this->ownerToken = $ownerToken; return $this; }

    public function getSlot(): ?AvailabilitySlot { return $this->slot; }
    public function setSlot(?AvailabilitySlot $slot): self { $this->slot = $slot; return $this; }
}
