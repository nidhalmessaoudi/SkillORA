<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaClient
{
    public function __construct(
        private HttpClientInterface $http
    ) {}

    public function generate(string $model, string $prompt): string
    {
        $response = $this->http->request('POST', 'http://localhost:11434/api/generate', [
            'json' => [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.3,
                ],
            ],
            'timeout' => 180, // 🔥 IMPORTANT : évite TimeoutException
        ]);

        $data = $response->toArray(false);

        if (!isset($data['response'])) {
            throw new \RuntimeException('Réponse Ollama invalide.');
        }

        return $data['response'];
    }
}