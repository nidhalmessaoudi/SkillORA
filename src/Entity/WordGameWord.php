<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'word_game_word')]
class WordGameWord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'words', targetEntity: WordGame::class)]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?WordGame $game = null;

    #[ORM\Column(type: 'string', length: 40)]
    private string $word = '';

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $points = 1;

    public function getId(): ?int { return $this->id; }

    public function getGame(): ?WordGame { return $this->game; }
    public function setGame(WordGame $game): static { $this->game = $game; return $this; }

    public function getWord(): string { return $this->word; }
    public function setWord(string $word): static { $this->word = strtoupper(trim($word)); return $this; }

    public function getPoints(): int { return $this->points; }
    public function setPoints(int $points): static { $this->points = $points; return $this; }
}