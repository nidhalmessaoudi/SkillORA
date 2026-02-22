<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'hangman_game')]
class HangmanGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // ex: "Symfony Hangman"
    #[ORM\Column(length: 120)]
    private string $title = 'Hangman';

    // ex: "symfony" | "python" | "database"
    #[ORM\Column(length: 40)]
    private string $topic = 'programming';

    // easy | medium | hard
    #[ORM\Column(length: 20)]
    private string $level = 'easy';

    // phrase / définition
    #[ORM\Column(type: 'text')]
    private string $hint = '';

    // mot secret (A-Z)
    #[ORM\Column(length: 30)]
    private string $answer = '';

    #[ORM\Column(type: 'smallint')]
    private int $maxMistakes = 7;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getTopic(): string { return $this->topic; }
    public function setTopic(string $topic): self { $this->topic = $topic; return $this; }

    public function getLevel(): string { return $this->level; }
    public function setLevel(string $level): self { $this->level = $level; return $this; }

    public function getHint(): string { return $this->hint; }
    public function setHint(string $hint): self { $this->hint = $hint; return $this; }

    public function getAnswer(): string { return $this->answer; }
    public function setAnswer(string $answer): self { $this->answer = strtoupper(preg_replace('/[^A-Z]/', '', $answer)); return $this; }

    public function getMaxMistakes(): int { return $this->maxMistakes; }
    public function setMaxMistakes(int $max): self { $this->maxMistakes = max(3, min(12, $max)); return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}