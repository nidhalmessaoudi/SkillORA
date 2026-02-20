<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_answer_embedding', columns: ['answer_id'])]
class SubmissionEmbedding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Answer $answer = null;

    #[ORM\Column(length: 64)]
    private string $contentHash = '';

    #[ORM\Column(type: 'json')]
    private array $embedding = [];

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getAnswer(): ?Answer { return $this->answer; }
    public function setAnswer(?Answer $answer): static { $this->answer = $answer; return $this; }

    public function getContentHash(): string { return $this->contentHash; }
    public function setContentHash(string $hash): static { $this->contentHash = $hash; return $this; }

    /** @return float[] */
    public function getEmbedding(): array { return $this->embedding; }
    /** @param float[] $embedding */
    public function setEmbedding(array $embedding): static { $this->embedding = $embedding; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
