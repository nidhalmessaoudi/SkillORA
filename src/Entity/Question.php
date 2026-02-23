<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $content = '';

    #[ORM\Column(length: 20)]
    private ?string $type = 'MCQ';

    #[ORM\Column]
    private int $score = 1;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evaluation $evaluation = null;

    #[ORM\OneToMany(
        mappedBy: 'question',
        targetEntity: Answer::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $answers;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type; return $this; }

    public function getScore(): int { return $this->score; }
    public function setScore(int $score): static { $this->score = $score; return $this; }

    public function getEvaluation(): ?Evaluation { return $this->evaluation; }
    public function setEvaluation(?Evaluation $evaluation): static { $this->evaluation = $evaluation; return $this; }

    /** @return Collection<int, Answer> */
    public function getAnswers(): Collection { return $this->answers; }

    public function addAnswer(Answer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setQuestion($this); // ✅ owning side
        }
        return $this;
    }

    public function removeAnswer(Answer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            // orphanRemoval true => supprimé automatiquement
        }
        return $this;
    }

    public function isMcq(): bool { return $this->type === 'MCQ'; }
    public function isText(): bool { return $this->type === 'TEXT'; }
}
