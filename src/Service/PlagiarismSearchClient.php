<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PlagiarismSearchClient
{
    public function __construct(
        private HttpClientInterface $http,
        private string $apiKey,
        private string $endpoint
    ) {}

    /**
     * @return array{percent:int, sources:array<int,array<string,mixed>>, raw:array<string,mixed>}
     */
    public function check(string $text): array
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) < 30) {
            return ['percent' => 0, 'sources' => [], 'raw' => []];
        }

        $res = $this->http->request('POST', $this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'text' => $text,
                // Certains plans acceptent des options :
                // 'language' => 'fr',
                // 'includeSources' => true,
                // 'sourcesLimit' => 5,
            ],
            'timeout' => 60,
        ]);

        $data = $res->toArray(false);

        // ⚠️ Les clés peuvent varier selon l’API.
        // Adaptation defensive :
        $percent = 0;
        if (isset($data['plagiarism'])) $percent = (int) round($data['plagiarism']);
        elseif (isset($data['percent'])) $percent = (int) round($data['percent']);
        elseif (isset($data['result']['percent'])) $percent = (int) round($data['result']['percent']);

        $sources = [];
        if (isset($data['sources']) && is_array($data['sources'])) $sources = $data['sources'];
        elseif (isset($data['result']['sources']) && is_array($data['result']['sources'])) $sources = $data['result']['sources'];

        return [
            'percent' => max(0, min(100, $percent)),
            'sources' => $sources,
            'raw' => $data,
        ];
    }
}