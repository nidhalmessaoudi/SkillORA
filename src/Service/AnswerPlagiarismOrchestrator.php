<?php

namespace App\Service;

use App\Entity\Answer;
use Doctrine\ORM\EntityManagerInterface;

class AnswerPlagiarismOrchestrator
{
    public function __construct(
        private EntityManagerInterface $em,
        private AiSuspicionScorer $aiScorer,
        private PlagiarismSearchClient $plagiarismSearchClient, // ✅ AJOUT
    ) {}

    public function analyzeAndSave(Answer $answer): Answer
    {
        $text = trim((string) $answer->getContent());

        // ✅ IA suspicion
        $ai = $this->aiScorer->score($text);
        $answer->setAiSuspicionPercent((int) ($ai['percent'] ?? 0));

        // ✅ Web plagiarism (PLAGIARISMSEARCH)
        $web = $this->plagiarismSearchClient->check($text);
        $answer->setWebPlagiarismPercent((int) ($web['percent'] ?? 0));
        $answer->setWebSources($web['sources'] ?? []);

        $answer->setLastPlagiarismCheckAt(new \DateTimeImmutable());

        $this->em->persist($answer);
        $this->em->flush();

        return $answer;
    }
}