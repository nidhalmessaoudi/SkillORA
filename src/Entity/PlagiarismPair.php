<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_eval', columns: ['evaluation_id'])]
#[ORM\Index(name: 'idx_run', columns: ['run_id'])]
class PlagiarismPair
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlagiarismRun $run = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Evaluation $evaluation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Answer $answerA = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Answer $answerB = null;

    // scores partiels
    #[ORM\Column(type: 'float')]
    private float $semantic = 0.0; // embeddings cosine

    #[ORM\Column(type: 'float')]
    private float $lexical = 0.0; // shingles/jaccard

    #[ORM\Column(type: 'float')]
    private float $structure = 0.0; // paragraph/sentence shape

    // score final
    #[ORM\Column(type: 'float')]
    private float $finalScore = 0.0; // 0..1

    #[ORM\Column(type: 'integer')]
    private int $plagiarismPercent = 0; // 0..100

    #[ORM\Column(length: 20)]
    private string $status = 'OK'; // OK | SUSPECT | HIGH

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $highlights = null; // ex: shared phrases/snippets

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // getters/setters (omits for brevity)
    public function setRun(?PlagiarismRun $run): static { $this->run = $run; return $this; }
    public function setEvaluation(?Evaluation $e): static { $this->evaluation = $e; return $this; }
    public function setAnswerA(?Answer $a): static { $this->answerA = $a; return $this; }
    public function setAnswerB(?Answer $b): static { $this->answerB = $b; return $this; }

    public function setSemantic(float $v): static { $this->semantic = $v; return $this; }
    public function setLexical(float $v): static { $this->lexical = $v; return $this; }
    public function setStructure(float $v): static { $this->structure = $v; return $this; }

    public function setFinalScore(float $v): static { $this->finalScore = $v; return $this; }
    public function setPlagiarismPercent(int $p): static { $this->plagiarismPercent = $p; return $this; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function setHighlights(?array $h): static { $this->highlights = $h; return $this; }
}
