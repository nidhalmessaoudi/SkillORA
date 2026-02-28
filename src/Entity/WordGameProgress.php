<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'word_game_progress')]
#[ORM\UniqueConstraint(name: 'uniq_user_game', columns: ['user_id', 'game_id'])]
class WordGameProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // user_id -> FK vers users.id
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    // game_id -> FK vers word_game.id
    #[ORM\ManyToOne(targetEntity: WordGame::class)]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?WordGame $game = null;

    // found_words JSON DEFAULT NULL
    /** @var list<string>|null */
    #[ORM\Column(name: 'found_words', type: 'json', nullable: true)]
    private ?array $foundWords = null;

    // score INT NOT NULL DEFAULT 0
    #[ORM\Column(name: 'score', type: 'integer', options: ['default' => 0])]
    private int $score = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getGame(): ?WordGame
    {
        return $this->game;
    }

    public function setGame(WordGame $game): static
    {
        $this->game = $game;
        return $this;
    }

    /** @return list<string> */
    public function getFoundWords(): array
    {
        return $this->foundWords ?? [];
    }

    /** @param list<string>|null $foundWords */
    public function setFoundWords(?array $foundWords): static
    {
        $this->foundWords = $foundWords;
        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;
        return $this;
    }
}
