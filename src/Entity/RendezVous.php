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

    public const TYPE_ONLINE = 'en_ligne';
    public const TYPE_IN_PERSON = 'en_personne';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "student_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?User $student = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "professor_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?User $professor = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(name: "course_id", referencedColumnName: "id", nullable: true, onDelete: "SET NULL")]
    private ?Course $course = null;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "en_attente"])]
    private string $statut = self::STATUS_EN_ATTENTE;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "en_ligne"])]
    private string $meetingType = self::TYPE_ONLINE;

    #[ORM\Column(type: "string", length: 500, nullable: true)]
    private ?string $meetingLink = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $locationLabel = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $locationLat = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $locationLng = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $refusalReason = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $coursePdfName = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToOne(inversedBy: "rendezVous", targetEntity: AvailabilitySlot::class)]
    #[ORM\JoinColumn(name: "slot_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE", unique: true)]
    private ?AvailabilitySlot $slot = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // ===== GETTERS & SETTERS =====

    public function getId(): ?int { return $this->id; }

    public function getStudent(): ?User { return $this->student; }
    public function setStudent(?User $student): self { $this->student = $student; return $this; }

    public function getProfessor(): ?User { return $this->professor; }
    public function setProfessor(?User $professor): self { $this->professor = $professor; return $this; }

    public function getCourse(): ?Course { return $this->course; }
    public function setCourse(?Course $course): self { $this->course = $course; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    public function getMeetingType(): string { return $this->meetingType; }
    public function setMeetingType(string $meetingType): self { $this->meetingType = $meetingType; return $this; }

    public function getMeetingLink(): ?string { return $this->meetingLink; }
    public function setMeetingLink(?string $meetingLink): self { $this->meetingLink = $meetingLink; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): self { $this->location = $location; return $this; }

    public function getLocationLabel(): ?string { return $this->locationLabel; }
    public function setLocationLabel(?string $locationLabel): self { $this->locationLabel = $locationLabel; return $this; }

    public function getLocationLat(): ?float { return $this->locationLat; }
    public function setLocationLat(?float $locationLat): self { $this->locationLat = $locationLat; return $this; }

    public function getLocationLng(): ?float { return $this->locationLng; }
    public function setLocationLng(?float $locationLng): self { $this->locationLng = $locationLng; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): self { $this->message = $message; return $this; }

    public function getRefusalReason(): ?string { return $this->refusalReason; }
    public function setRefusalReason(?string $refusalReason): self { $this->refusalReason = $refusalReason; return $this; }

    public function getCoursePdfName(): ?string { return $this->coursePdfName; }
    public function setCoursePdfName(?string $coursePdfName): self { $this->coursePdfName = $coursePdfName; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getSlot(): ?AvailabilitySlot { return $this->slot; }
    public function setSlot(?AvailabilitySlot $slot): self { $this->slot = $slot; return $this; }
}
