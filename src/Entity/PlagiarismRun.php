<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PlagiarismRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Evaluation $evaluation = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'integer')]
    private int $submissionsCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $pairsCount = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $thresholds = null; // store config used

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEvaluation(): ?Evaluation { return $this->evaluation; }
    public function setEvaluation(?Evaluation $evaluation): static { $this->evaluation = $evaluation; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getSubmissionsCount(): int { return $this->submissionsCount; }
    public function setSubmissionsCount(int $c): static { $this->submissionsCount = $c; return $this; }

    public function getPairsCount(): int { return $this->pairsCount; }
    public function setPairsCount(int $c): static { $this->pairsCount = $c; return $this; }

    public function getThresholds(): ?array { return $this->thresholds; }
    public function setThresholds(?array $t): static { $this->thresholds = $t; return $this; }
}
