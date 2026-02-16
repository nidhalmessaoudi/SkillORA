<?php

namespace App\Entity;

use App\Repository\AnswerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnswerRepository::class)]
class Answer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(nullable: true)]
    private ?bool $isCorrect = null;

    #[ORM\Column(length: 20)]
    private string $role; // CHOICE | SUBMISSION

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false)]
    private Question $question;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $student = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;


    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /* ===================== */
    /* GETTERS / SETTERS     */
    /* ===================== */

    public function getId(): ?int { return $this->id; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getIsCorrect(): ?bool { return $this->isCorrect; }
    public function setIsCorrect(?bool $isCorrect): static { $this->isCorrect = $isCorrect; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getQuestion(): Question { return $this->question; }
    public function setQuestion(Question $question): static { $this->question = $question; return $this; }

    public function getStudent(): ?User { return $this->student; }
    public function setStudent(?User $student): static { $this->student = $student; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isChoice(): bool { return $this->role === 'CHOICE'; }

    public function isSubmission(): bool { return $this->role === 'SUBMISSION'; }
    public function isCorrectAnswer(): bool { return $this->isCorrect === true; }
}
