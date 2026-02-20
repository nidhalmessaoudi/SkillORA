<?php

namespace App\Service;

use App\Entity\Answer;
use Doctrine\ORM\EntityManagerInterface;

class AnswerPlagiarismOrchestrator
{
    public function __construct(
        private EntityManagerInterface $em,
        private AiSuspicionScorer $aiScorer
    ) {}

    public function analyzeAndSave(Answer $answer): Answer
    {
        $text = (string) $answer->getContent();

        $ai = $this->aiScorer->score($text);
        $answer->setAiSuspicionPercent((int) $ai['percent']);

        $answer->setLastPlagiarismCheckAt(new \DateTimeImmutable());

        $this->em->persist($answer);
        $this->em->flush();

        return $answer;
    }
}