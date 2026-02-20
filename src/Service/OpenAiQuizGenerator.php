<?php

namespace App\Service;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use OpenAI;

class OpenAiQuizGenerator
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $openAiApiKey,
    ) {}

    /**
     * @return Question[]
     */
    public function generateMcq(Evaluation $evaluation, string $topic, int $count = 5): array
    {
        if (strtoupper((string) $evaluation->getType()) !== 'QUIZ') {
            throw new \RuntimeException('IA uniquement pour les QUIZ.');
        }

        // ✅ Empêcher double génération
        $existing = $this->em->getRepository(Question::class)->count(['evaluation' => $evaluation]);
        if ($existing > 0) {
            throw new \RuntimeException('Questions déjà générées pour ce quiz.');
        }

        $count = max(1, min(20, $count));
        $topic = trim($topic) ?: 'Symfony framework';

        if (!$this->openAiApiKey || !str_starts_with($this->openAiApiKey, 'sk-')) {
            throw new \RuntimeException('OPENAI_API_KEY manquante ou invalide.');
        }

        $client = OpenAI::client($this->openAiApiKey);

        // ✅ JSON Schema (strict)
        $schema = [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'minItems' => $count,
                    'maxItems' => $count,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'options' => [
                                'type' => 'array',
                                'minItems' => 4,
                                'maxItems' => 4,
                                'items' => ['type' => 'string'],
                            ],
                            'correct_index' => [
                                'type' => 'integer',
                                'minimum' => 0,
                                'maximum' => 3,
                            ],
                            'explanation' => ['type' => 'string'],
                        ],
                        'required' => ['question', 'options', 'correct_index', 'explanation'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['questions'],
            'additionalProperties' => false,
        ];

        $prompt = <<<TXT
Génère {$count} questions QCM sur: {$topic}
Règles:
- 4 choix EXACTEMENT
- 1 seule bonne réponse
- Explication: 1 phrase courte
- Niveau: intermédiaire
Retourne UNIQUEMENT un JSON conforme au schéma.
TXT;

        $response = $this->callWithRetry(function () use ($client, $prompt, $schema) {
            return $client->responses()->create([
                'model' => 'gpt-4.1-mini',
                'input' => $prompt,

                // ✅ Limite sortie pour éviter TPM rate limit
                'max_output_tokens' => 600,

                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'quiz_mcq',
                        'schema' => $schema,
                        // si ton SDK le supporte, tu peux ajouter strict:
                        // 'strict' => true,
                    ],
                ],
            ]);
        });

        $arr = method_exists($response, 'toArray') ? $response->toArray() : (array) $response;

        $json = $arr['output_text'] ?? null;
        if (!$json && isset($arr['output'][0]['content'][0]['text'])) {
            $json = $arr['output'][0]['content'][0]['text'];
        }

        if (!is_string($json) || trim($json) === '') {
            throw new \RuntimeException('Réponse OpenAI vide.');
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['questions']) || !is_array($data['questions'])) {
            throw new \RuntimeException('JSON invalide (questions manquantes).');
        }

        $created = [];

        foreach ($data['questions'] as $q) {
            if (
                !isset($q['question'], $q['options'], $q['correct_index'], $q['explanation']) ||
                !is_array($q['options']) ||
                count($q['options']) !== 4
            ) {
                continue;
            }

            $correct = (int) $q['correct_index'];
            if ($correct < 0 || $correct > 3) {
                continue;
            }

            $question = (new Question())
                ->setEvaluation($evaluation)
                ->setType('MCQ')
                ->setScore(1)
                ->setContent(trim((string) $q['question']))
                ->setExplanation(trim((string) $q['explanation']));

            foreach ($q['options'] as $i => $opt) {
                $answer = (new Answer())
                    ->setRole('CHOICE')
                    ->setContent(trim((string) $opt))
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

    private function callWithRetry(callable $fn, int $maxRetries = 2)
    {
        $delaySeconds = 2;

        for ($i = 0; $i <= $maxRetries; $i++) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                $msg = strtolower($e->getMessage());
                $isRateLimit =
                    str_contains($msg, 'rate limit') ||
                    str_contains($msg, 'too many requests') ||
                    str_contains($msg, '429');

                if ($isRateLimit && $i < $maxRetries) {
                    sleep($delaySeconds);
                    $delaySeconds *= 2; // 2s, 4s, 8s
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('OpenAI failed after retries.');
    }
}