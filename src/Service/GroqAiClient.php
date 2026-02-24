<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqAiClient
{
    public function __construct(
        private HttpClientInterface $http,
        private LoggerInterface $logger,
        private string $apiKey,
        private string $model,
    ) {}

    /**
     * @return array{score:int, feedback:string}
     */
    public function gradeExam(string $title, int $totalScore, string $questionsText, string $studentAnswer): array
    {
        $key = trim((string) $this->apiKey);
        // sécurité: si quelqu’un colle "Bearer xxx" dans .env
        $key = preg_replace('/^Bearer\s+/i', '', $key);

        if ($key === '') {
            throw new \RuntimeException('GROQ_API_KEY est vide ou non injectée.');
        }

        $prompt = $this->buildPrompt($title, $totalScore, $questionsText, $studentAnswer);

        $payload = [
            'model' => $this->model ?: 'llama3-70b-8192',
            'messages' => [
                ['role' => 'system', 'content' => 'Tu es un correcteur d’examen strict et juste.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
        ];

        // Groq = OpenAI compatible => /openai/v1/chat/completions
        $response = $this->http->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'json' => $payload,
        ]);

        $status = $response->getStatusCode();
        $raw = $response->getContent(false);

        // log utile si erreur (401/429/etc)
        $this->logger->info('Groq response', [
            'status' => $status,
            'raw' => mb_substr($raw, 0, 2000),
        ]);

        if ($status < 200 || $status >= 300) {
            $data = json_decode($raw, true);
            $msg = $data['error']['message'] ?? $data['message'] ?? $data['detail'] ?? ('HTTP ' . $status);
            throw new \RuntimeException('Groq API error: ' . $msg);
        }

        $data = json_decode($raw, true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Groq: réponse vide');
        }

        // On attend du JSON
        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            return ['score' => 0, 'feedback' => $content];
        }

        $score = (int) ($parsed['score'] ?? 0);
        $feedback = (string) ($parsed['feedback'] ?? '');

        if ($score < 0) $score = 0;
        if ($score > $totalScore) $score = $totalScore;

        return ['score' => $score, 'feedback' => $feedback];
    }

    private function buildPrompt(string $title, int $totalScore, string $questionsText, string $studentAnswer): string
    {
        return <<<TXT
Examen: {$title}
Barème total: {$totalScore}

SUJET / QUESTIONS:
{$questionsText}

RÉPONSE DE L'ÉTUDIANT:
{$studentAnswer}

Consignes:
- Donne une NOTE sur {$totalScore}.
- Donne un FEEDBACK clair (points forts, erreurs, ce qui manque).
- Réponds STRICTEMENT en JSON valide, sans texte autour, format:
{"score": 12, "feedback": "..."}
TXT;
    }
}