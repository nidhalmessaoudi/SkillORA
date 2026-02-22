<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class WordGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(length: 40)]
    private string $theme = 'programming'; // symfony | python | programming

    #[ORM\Column(length: 30)]
    private string $letters = ''; // ex: "MOTION"

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: WordGameWord::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $words;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->words = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getTheme(): string { return $this->theme; }
    public function setTheme(string $theme): static { $this->theme = $theme; return $this; }

    public function getLetters(): string { return $this->letters; }
    public function setLetters(string $letters): static { $this->letters = strtoupper(preg_replace('/\s+/', '', $letters)); return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, WordGameWord> */
    public function getWords(): Collection { return $this->words; }

    public function addWord(WordGameWord $word): static
    {
        if (!$this->words->contains($word)) {
            $this->words->add($word);
            $word->setGame($this);
        }
        return $this;
    }
}