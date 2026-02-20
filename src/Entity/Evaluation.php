<?php

namespace App\Entity;

use App\Repository\EvaluationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvaluationRepository::class)]
class Evaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Le titre doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le titre ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: "La description ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $description = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank(message: "Le type est obligatoire.")]
    #[Assert\Choice(
        choices: ['QUIZ', 'EXAM'],
        message: "Type invalide. Choisis soit QUIZ soit EXAM."
    )]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "La durée est obligatoire.")]
    #[Assert\Type(type: "integer", message: "La durée doit être un nombre entier.")]
    #[Assert\Positive(message: "La durée doit être supérieure à 0.")]
    #[Assert\Range(
        min: 1,
        max: 300,
        notInRangeMessage: "La durée doit être entre {{ min }} et {{ max }} minutes."
    )]
    private ?int $duration = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Le score total est obligatoire.")]
    #[Assert\Type(type: "integer", message: "Le score total doit être un nombre entier.")]
    #[Assert\Positive(message: "Le score total doit être supérieur à 0.")]
    #[Assert\Range(
        min: 1,
        max: 1000,
        notInRangeMessage: "Le score total doit être entre {{ min }} et {{ max }}."
    )]
    private int $totalScore = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'docx_path', length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le chemin DOCX ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $docxPath = null;

    // ✅ PDF sans aucun contrôle obligatoire
    #[ORM\Column(name: 'pdf_path', length: 255, nullable: true)]
    private ?string $pdfPath = null;

    #[ORM\OneToMany(mappedBy: 'evaluation', targetEntity: Question::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $questions;

    #[ORM\OneToMany(mappedBy: 'evaluation', targetEntity: UserEvaluation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $userEvaluations;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->userEvaluations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): static { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type; return $this; }

    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $duration): static { $this->duration = $duration; return $this; }

    public function getTotalScore(): int { return $this->totalScore; }
    public function setTotalScore(int $totalScore): static { $this->totalScore = $totalScore; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getDocxPath(): ?string { return $this->docxPath; }
    public function setDocxPath(?string $docxPath): static { $this->docxPath = $docxPath; return $this; }

    public function getPdfPath(): ?string { return $this->pdfPath; }
    public function setPdfPath(?string $pdfPath): static { $this->pdfPath = $pdfPath; return $this; }

    public function getQuestions(): Collection { return $this->questions; }
    public function getUserEvaluations(): Collection { return $this->userEvaluations; }

    public function calculateTotalScore(): void
    {
        $total = 0;
        foreach ($this->questions as $question) {
            $total += (int) $question->getScore();
        }
        $this->totalScore = $total;
    }
}