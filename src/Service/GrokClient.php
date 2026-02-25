<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GrokClient
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function generate(string $systemPrompt, string $userMessage): string
    {
        $fallbackModels = [
            $this->model,
            'llama-3.3-70b-versatile',
            'llama-3.1-8b-instant',
            'mixtral-8x7b-32768',
            'gemma2-9b-it',
        ];

        $models = [];
        foreach ($fallbackModels as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || in_array($candidate, $models, true)) {
                continue;
            }
            $models[] = $candidate;
        }

        $lastError = null;

        foreach ($models as $model) {
            $attempt = $this->chatCompletion($model, $systemPrompt, $userMessage);
            if ($attempt['ok']) {
                return $attempt['text'];
            }

            $lastError = $attempt['error'];
            if (!$attempt['modelNotFound']) {
                break;
            }
        }

        if ($lastError !== null && str_contains(strtolower($lastError), 'model')) {
            $discoveredModels = $this->listAvailableModels();
            foreach ($discoveredModels as $model) {
                $attempt = $this->chatCompletion($model, $systemPrompt, $userMessage);
                if ($attempt['ok']) {
                    return $attempt['text'];
                }
                $lastError = $attempt['error'];
            }
        }

        throw new \RuntimeException($lastError ?? 'Unknown Groq error.');
    }

    /**
     * @return array{ok: bool, text: string, error: string, modelNotFound: bool}
     */
    private function chatCompletion(string $model, string $systemPrompt, string $userMessage): array
    {
        $response = $this->http->request(
            'POST',
            'https://api.groq.com/openai/v1/chat/completions',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'temperature' => 0.2,
                ],
                'timeout' => 30,
            ]
        );

        $status = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($status >= 400) {
            $errorPayload = $data['error']['message'] ?? $data['error'] ?? json_encode($data);
            $normalized = strtolower((string) $errorPayload);

            return [
                'ok' => false,
                'text' => '',
                'error' => 'Status: ' . $status . ' | Response: ' . json_encode($data),
                'modelNotFound' => str_contains($normalized, 'model not found')
                    || str_contains($normalized, 'does not exist')
                    || str_contains($normalized, 'decommissioned'),
            ];
        }

        $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($text === '') {
            return [
                'ok' => false,
                'text' => '',
                'error' => 'Status: ' . $status . ' | Empty response from model: ' . $model,
                'modelNotFound' => false,
            ];
        }

        return [
            'ok' => true,
            'text' => $text,
            'error' => '',
            'modelNotFound' => false,
        ];
    }

    /**
     * @return string[]
     */
    private function listAvailableModels(): array
    {
        try {
            $response = $this->http->request(
                'GET',
                'https://api.groq.com/openai/v1/models',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 20,
                ]
            );

            if ($response->getStatusCode() >= 400) {
                return [];
            }

            $data = $response->toArray(false);
            if (!isset($data['data']) || !is_array($data['data'])) {
                return [];
            }

            $models = [];
            foreach ($data['data'] as $item) {
                if (!is_array($item) || !isset($item['id']) || !is_string($item['id'])) {
                    continue;
                }

                $id = trim($item['id']);
                if ($id === '') {
                    continue;
                }

                $idLower = strtolower($id);
                if (
                    !str_contains($idLower, 'llama')
                    && !str_contains($idLower, 'mixtral')
                    && !str_contains($idLower, 'gemma')
                    && !str_contains($idLower, 'qwen')
                ) {
                    continue;
                }

                if (str_contains($idLower, 'vision') || str_contains($idLower, 'image')) {
                    continue;
                }

                $models[] = $id;
            }

            return array_values(array_unique($models));
        } catch (\Throwable) {
            return [];
        }
    }
}
