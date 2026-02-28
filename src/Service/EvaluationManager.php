<?php

namespace App\Service;

use App\Entity\Evaluation;

class EvaluationManager
{
    // Règle 1 : type obligatoire et doit être QUIZ ou EXAM
    public function validateType(Evaluation $evaluation): bool
    {
        $type = $evaluation->getType();

        if (empty($type)) {
            throw new \InvalidArgumentException('Le type est obligatoire');
        }

        if (!in_array($type, ['QUIZ', 'EXAM'], true)) {
            throw new \InvalidArgumentException('Type invalide. Choisis soit QUIZ soit EXAM.');
        }

        return true;
    }

    // Règle 2 : totalScore = somme des scores des questions
    public function validateTotalScore(Evaluation $evaluation): bool
    {
        // calcule totalScore depuis questions
        $evaluation->calculateTotalScore();

        $sum = 0;
        foreach ($evaluation->getQuestions() as $question) {
            $sum += (int) $question->getScore();
        }

        if ($evaluation->getTotalScore() !== $sum) {
            throw new \InvalidArgumentException('Le score total ne correspond pas à la somme des scores des questions');
        }

        return true;
    }
}