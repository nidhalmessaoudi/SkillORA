<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAIEmbeddingsClient
{
    public function __construct(
        private HttpClientInterface $http,
        private string $openaiApiKey,
        private string $model = 'text-embedding-3-small'
    ) {}

    /** @return float[] */
    public function embed(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];

        $res = $this->http->request('POST', 'https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->openaiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'input' => $text,
            ],
            'timeout' => 30,
        ]);

        $data = $res->toArray(false);
        if (!isset($data['data'][0]['embedding'])) {
            throw new \RuntimeException('Invalid embeddings response');
        }

        return array_map('floatval', $data['data'][0]['embedding']);
    }
}
