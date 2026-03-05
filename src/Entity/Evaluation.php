<?php

namespace App\Entity;

use App\Repository\EvaluationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvaluationRepository::class)]
class Evaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $type; // QUIZ | EXAM

    #[ORM\Column]
    private int $duration; // en minutes

    #[ORM\Column]
    private int $totalScore = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

   

    #[ORM\OneToMany(
        mappedBy: 'evaluation',
        targetEntity: Question::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private Collection $questions;

    #[ORM\OneToMany(mappedBy: 'evaluation', targetEntity: UserEvaluation::class)]
    private Collection $userEvaluations;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->userEvaluations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /* ===================== */
    /* GETTERS / SETTERS     */
    /* ===================== */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getTotalScore(): int
    {
        return $this->totalScore;
    }

    public function setTotalScore(int $totalScore): static
    {
        $this->totalScore = $totalScore;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function getUserEvaluations(): Collection
    {
        return $this->userEvaluations;
    }

    public function addUserEvaluation(UserEvaluation $userEvaluation): static
    {
        if (!$this->userEvaluations->contains($userEvaluation)) {
            $this->userEvaluations->add($userEvaluation);
            $userEvaluation->setEvaluation($this);
        }

        return $this;
    }

    public function removeUserEvaluation(UserEvaluation $userEvaluation): static
    {
        if ($this->userEvaluations->removeElement($userEvaluation)) {
            if ($userEvaluation->getEvaluation() === $this) {
                $userEvaluation->setEvaluation(null);
            }
        }

        return $this;
    }

    /* ===================== */
    /* LOGIQUE MÉTIER 🔥     */
    /* ===================== */

    public function calculateTotalScore(): void
    {
        $total = 0;

        foreach ($this->questions as $question) {
            $total += $question->getScore();
        }

        $this->totalScore = $total;
    }
}

