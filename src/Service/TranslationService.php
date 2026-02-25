<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $hfKey;

    // Use the working Inference API endpoint
    private const API_BASE_URL = 'https://router.huggingface.co/hf-inference/models';
private const MODEL = 'facebook/mbart-large-50-many-to-many-mmt';

    // Language mapping for mBART
    private array $langMap = [
        'en' => 'en_XX',
        'fr' => 'fr_XX',
        'ar' => 'ar_AR',
        'es' => 'es_XX',
        'de' => 'de_DE',
        'it' => 'it_IT',
        'pt' => 'pt_XX',
        'ru' => 'ru_RU',
        'zh' => 'zh_CN',
        'nl' => 'nl_XX',
    ];

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $hfKey
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->hfKey = $hfKey;
    }

    public function translateForItem(
        string $text,
        string $targetLang,
        string $itemType,
        int $itemId,
        ?string $sourceLang = null
    ): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (empty($this->hfKey)) {
            throw new \RuntimeException('HuggingFace API key not configured');
        }

        $targetCode = $this->mapToMbart($targetLang);
        $sourceCode = $sourceLang && $sourceLang !== 'auto' 
            ? $this->mapToMbart($sourceLang) 
            : 'en_XX';

        $url = self::API_BASE_URL . '/' . self::MODEL;

        $this->logger->info('Translation request', [
            'model' => self::MODEL,
            'source' => $sourceCode,
            'target' => $targetCode,
            'url' => $url
        ]);

        try {
            // mBART expects this format
            $payload = [
                'inputs' => $text,
                'parameters' => [
                    'src_lang' => $sourceCode,
                    'tgt_lang' => $targetCode,
                ],
                'options' => [
                    'wait_for_model' => true,
                    'use_cache' => true
                ]
            ];

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->hfKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 90, // Increased timeout for model loading
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $content = $response->getContent(false);
                $this->logger->error('HuggingFace API error', [
                    'status' => $statusCode,
                    'response' => $content
                ]);
                throw new \RuntimeException("Translation API error (HTTP $statusCode): $content");
            }

            $data = $response->toArray(false);

            // mBART returns array with translation_text
            if (isset($data[0]['translation_text'])) {
                return $data[0]['translation_text'];
            }

            // Check for error
            if (isset($data['error'])) {
                // Model might be loading
                if (stripos($data['error'], 'loading') !== false) {
                    throw new \RuntimeException('Translation model is loading. Please wait 30-60 seconds and try again.');
                }
                throw new \RuntimeException('Translation error: ' . $data['error']);
            }

            $this->logger->error('Unexpected response format', ['data' => $data]);
            throw new \RuntimeException('Unexpected response format from translation API');

        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            $this->logger->error('Network error', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Network error: ' . $e->getMessage(), 0, $e);
        }
    }

    private function mapToMbart(string $shortCode): string
    {
        $shortCode = strtolower($shortCode);
        return $this->langMap[$shortCode] ?? 'en_XX';
    }

    public function getSupportedLanguages(): array
    {
        return array_keys($this->langMap);
    }
}