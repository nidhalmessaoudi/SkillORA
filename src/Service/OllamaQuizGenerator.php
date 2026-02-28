<?php

namespace App\Service;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

class OllamaQuizGenerator
{
    public function __construct(
        private EntityManagerInterface $em,
        private OllamaClient $ollama,
    ) {}

    /**
     * @return list<Question>
     */
    public function generateMcq(Evaluation $evaluation, string $topic, int $count = 5): array
    {
        if (strtoupper((string) $evaluation->getType()) !== 'QUIZ') {
            throw new \RuntimeException('IA uniquement pour les QUIZ.');
        }

        $existing = $this->em->getRepository(Question::class)
            ->count(['evaluation' => $evaluation]);

        if ($existing > 0) {
            throw new \RuntimeException('Questions déjà générées.');
        }

        $count = max(1, min(10, $count));
        $topic = trim($topic) ?: 'Any topic';

        $prompt = <<<PROMPT
Tu es un générateur de QCM.

Génère {$count} questions QCM sur : {$topic}

IMPORTANT :
- Retourne UNIQUEMENT un JSON valide
- Pas de texte avant ou après
- Format exact :

{
  "questions": [
    {
      "question": "texte",
      "options": ["A", "B", "C", "D"],
      "correct_index": 0,
      "explanation": "phrase courte"
    }
  ]
}

Règles :
- 4 options EXACTEMENT
- correct_index entre 0 et 3
- explanation = 1 phrase courte
PROMPT;

       // $raw = $this->ollama->generate('mistral', $prompt);
       $raw = $this->ollama->generate('mistral:latest', $prompt);

        $json = $this->extractJson($raw);

        $data = json_decode($json, true);

        if (!$data || !isset($data['questions'])) {
            throw new \RuntimeException('JSON invalide généré par Ollama.');
        }

        $created = [];

        foreach ($data['questions'] as $q) {

            if (
                !isset($q['question'], $q['options'], $q['correct_index']) ||
                count($q['options']) !== 4
            ) {
                continue;
            }

            $correct = (int) $q['correct_index'];

            $question = (new Question())
                ->setEvaluation($evaluation)
                ->setType('MCQ')
                ->setScore(1)
                ->setContent(trim($q['question']))
                ->setExplanation($q['explanation'] ?? '');

            foreach ($q['options'] as $i => $opt) {
                $answer = (new Answer())
                    ->setRole('CHOICE')
                    ->setContent(trim($opt))
                    ->setIsCorrect($i === $correct);

                $question->addAnswer($answer);
            }

            $this->em->persist($question);
            $created[] = $question;
        }

        $evaluation->calculateTotalScore();
        $this->em->flush();

        return $created;
    }

    private function extractJson(string $text): string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false) {
            throw new \RuntimeException('JSON introuvable dans la réponse.');
        }

        return substr($text, $start, $end - $start + 1);
    }
}
