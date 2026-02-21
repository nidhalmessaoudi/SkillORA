<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'evaluation_audio_attempt')]
#[ORM\UniqueConstraint(name: 'uniq_user_evaluation', columns: ['user_id', 'evaluation_id'])]
#[ORM\HasLifecycleCallbacks]
class EvaluationAudioAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // user_id -> FK vers users.id
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    // evaluation_id -> FK vers evaluation.id
    #[ORM\ManyToOne(targetEntity: Evaluation::class)]
    #[ORM\JoinColumn(name: 'evaluation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Evaluation $evaluation = null;

    // play_count
    #[ORM\Column(name: 'play_count', type: 'integer', options: ['default' => 0])]
    private int $playCount = 0;

    // updated_at
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getEvaluation(): ?Evaluation
    {
        return $this->evaluation;
    }

    public function setEvaluation(Evaluation $evaluation): self
    {
        $this->evaluation = $evaluation;
        return $this;
    }

    public function getPlayCount(): int
    {
        return $this->playCount;
    }

    public function setPlayCount(int $c): self
    {
        $this->playCount = $c;
        return $this;
    }

    public function inc(): self
    {
        $this->playCount++;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}