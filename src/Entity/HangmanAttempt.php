<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'hangman_attempt')]
#[ORM\UniqueConstraint(name: 'uniq_user_game', columns: ['user_id', 'game_id'])]
class HangmanAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    // IMPORTANT: users.id = INT UNSIGNED => on force unsigned ici
    #[ORM\Column(name: 'user_id', type: 'integer', options: ['unsigned' => true], insertable: false, updatable: false)]
    private ?int $userId = null;
    // ⚠️ On garde la relation comme avant (user), mais l'id est mappé pour unsigned (voir note plus bas)

    #[ORM\ManyToOne(targetEntity: HangmanGame::class)]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?HangmanGame $game = null;

    #[ORM\Column(type: 'json')]
    private array $guessed = [];

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $mistakes = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $won = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $lost = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $score = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // ✅ Correction: garder aussi la relation User (Doctrine l’utilise)
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getGame(): ?HangmanGame { return $this->game; }
    public function setGame(HangmanGame $game): self { $this->game = $game; return $this; }

    public function getGuessed(): array { return $this->guessed; }
    public function setGuessed(array $g): self { $this->guessed = array_values(array_unique($g)); return $this; }

    public function getMistakes(): int { return $this->mistakes; }
    public function setMistakes(int $m): self { $this->mistakes = max(0, $m); return $this; }

    public function isWon(): bool { return $this->won; }
    public function setWon(bool $v): self { $this->won = $v; return $this; }

    public function isLost(): bool { return $this->lost; }
    public function setLost(bool $v): self { $this->lost = $v; return $this; }

    public function getScore(): int { return $this->score; }
    public function setScore(int $s): self { $this->score = max(0, $s); return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}