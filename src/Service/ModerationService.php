<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ModerationService
{
    private const HF_API_URL = 'https://router.huggingface.co/hf-inference/models/unitary/toxic-bert';
    private const TIMEOUT_SECONDS = 25;
    private const THRESHOLD = 0.60; // tune for your demo (0.6 is reasonable for "i hate everyone")

    private string $apiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        string $huggingFaceApiKey,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->apiKey = $huggingFaceApiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * @param string $content
     * @return array{safe:bool, categories:array, scores:array, message:string}
     */
    public function moderateContent(string $content): array
    {
        if (empty($this->apiKey)) {
            $this->logger->warning('HuggingFace API key not configured.');
            return $this->failOpen('API key not configured');
        }

        try {
            $response = $this->httpClient->request('POST', self::HF_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'inputs' => $content,
                    // wait_for_model reduces transient "loading" errors (may increase first-call latency)
                    'options' => ['wait_for_model' => true],
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $data = $response->toArray(false);

            // Generic HF error
            if (isset($data['error'])) {
                $this->logger->warning('HuggingFace returned error', ['error' => $data['error']]);
                return $this->failOpen('Moderation temporarily unavailable');
            }

            // Normalise prediction shape:
            // Accept either: [{label,score}, ...]  OR  [[{label,score}, ...]]
            if (isset($data[0]) && is_array($data[0]) && isset($data[0][0]['label'])) {
                $predictions = $data[0];
            } elseif (is_array($data) && isset($data[0]['label'])) {
                $predictions = $data;
            } else {
                // Unexpected response
                $this->logger->warning('Unexpected HuggingFace response shape', ['raw' => $data]);
                return $this->failOpen('Moderation temporarily unavailable');
            }

            $scores = [];
            $violations = [];

            foreach ($predictions as $prediction) {
                $label = (string) ($prediction['label'] ?? '');
                $score = (float) ($prediction['score'] ?? 0.0);

                // Normalise label (some models use uppercase / spaces)
                $labelNormalized = strtolower(str_replace(' ', '_', $label));
                $scores[$labelNormalized] = $score;

                // Consider these labels as blocking categories
                if (in_array($labelNormalized, ['toxic', 'insult', 'threat', 'severe_toxic', 'obscene'], true)
                    && $score >= self::THRESHOLD) {
                    $violations[] = $this->mapLabelToFriendlyName($labelNormalized);
                }
            }

            $violations = array_values(array_unique($violations));
            $isSafe = empty($violations);

            if (!$isSafe) {
                $this->logger->warning('Content flagged by HuggingFace', [
                    'scores' => $scores,
                    'preview' => mb_substr($content, 0, 120),
                ]);
            }

            return [
                'safe' => $isSafe,
                'categories' => $violations,
                'scores' => $scores,
                'message' => $isSafe ? '' : 'Your content contains: ' . implode(', ', $violations) . '. Please revise it.'
            ];
        } catch (\Throwable $e) {
            $this->logger->error('HuggingFace moderation exception', [
                'error' => $e->getMessage()
            ]);

            return $this->failOpen('Moderation service unavailable');
        }
    }

    private function mapLabelToFriendlyName(string $label): string
    {
        $map = [
            'toxic' => 'toxic language',
            'severe_toxic' => 'severe toxic language',
            'insult' => 'insults',
            'obscene' => 'obscene language',
            'threat' => 'threats',
        ];

        return $map[$label] ?? str_replace('_', ' ', $label);
    }

    private function failOpen(string $message): array
    {
        return [
            'safe' => true,
            'categories' => [],
            'scores' => [],
            'message' => $message
        ];
    }

    public function moderatePost(string $title, string $content): array
    {
        return $this->moderateContent("Title: $title\n\nContent: $content");
    }
}