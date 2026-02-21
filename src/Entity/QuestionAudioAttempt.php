<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'question_audio_attempt')]
#[ORM\UniqueConstraint(name: 'uniq_user_question', columns: ['user_id', 'question_id'])]
#[ORM\HasLifecycleCallbacks]
class QuestionAudioAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Force le nom user_id (important) + référence vers users.id
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    // Force le nom question_id (important) + référence vers question.id
    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(name: 'question_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Question $question = null;

    // Force le nom play_count
    #[ORM\Column(name: 'play_count', type: 'integer', options: ['default' => 0])]
    private int $playCount = 0;

    // Force le nom updated_at
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

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(Question $question): self
    {
        $this->question = $question;
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