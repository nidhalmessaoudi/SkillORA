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

    // ✅ Plagiarism results (garder)
    #[ORM\Column(nullable: true)]
    private ?int $webPlagiarismPercent = null;

    #[ORM\Column(nullable: true)]
    private ?int $aiSuspicionPercent = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $webSources = null;

    #[ORM\Column(nullable: true)]
private ?int $pasteCount = 0;

#[ORM\Column(nullable: true)]
private ?int $tabSwitchCount = 0;

#[ORM\Column(nullable: true)]
private ?\DateTimeImmutable $lastIntegrityEventAt = null;

#[ORM\Column(nullable: true)]
private ?\DateTimeImmutable $lastPlagiarismCheckAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getWebPlagiarismPercent(): ?int { return $this->webPlagiarismPercent; }
    public function setWebPlagiarismPercent(?int $p): static { $this->webPlagiarismPercent = $p; return $this; }

    public function getAiSuspicionPercent(): ?int { return $this->aiSuspicionPercent; }
    public function setAiSuspicionPercent(?int $p): static { $this->aiSuspicionPercent = $p; return $this; }

    public function getWebSources(): ?array { return $this->webSources; }
    public function setWebSources(?array $s): static { $this->webSources = $s; return $this; }


    public function getPasteCount(): ?int { return $this->pasteCount; }
public function setPasteCount(?int $pasteCount): static { $this->pasteCount = $pasteCount; return $this; }
public function incPasteCount(): static { $this->pasteCount = ($this->pasteCount ?? 0) + 1; $this->touchIntegrity(); return $this; }

public function getTabSwitchCount(): ?int { return $this->tabSwitchCount; }
public function setTabSwitchCount(?int $tabSwitchCount): static { $this->tabSwitchCount = $tabSwitchCount; return $this; }
public function incTabSwitchCount(): static { $this->tabSwitchCount = ($this->tabSwitchCount ?? 0) + 1; $this->touchIntegrity(); return $this; }

public function getLastIntegrityEventAt(): ?\DateTimeImmutable { return $this->lastIntegrityEventAt; }
public function setLastIntegrityEventAt(?\DateTimeImmutable $dt): static { $this->lastIntegrityEventAt = $dt; return $this; }

public function getLastPlagiarismCheckAt(): ?\DateTimeImmutable { return $this->lastPlagiarismCheckAt; }
public function setLastPlagiarismCheckAt(?\DateTimeImmutable $dt): static { $this->lastPlagiarismCheckAt = $dt; return $this; }

private function touchIntegrity(): void
{
    $this->lastIntegrityEventAt = new \DateTimeImmutable();
}
}
