<?php
// src/Service/AIGeneratorGroqService.php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AIGeneratorService
{
    private const GROQ_BASE = 'https://api.groq.com/openai/v1/chat/completions';
    private const TIMEOUT = 60;

    private string $apiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private ModerationService $moderationService;
    private string $modelId;

    public function __construct(
        string $groqApiKey,
        string $groqModelId,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ModerationService $moderationService
    ) {
        $this->apiKey = $groqApiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->moderationService = $moderationService;
        $this->modelId = $groqModelId;
    }

    /**
     * Generate a forum post from $prompt.
     * Returns the same structure your app expects:
     * ['success'=>bool,'title'=>string,'content'=>string,'tags'=>array,'error'=>string?]
     */
    public function generatePost(string $prompt): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'Groq API key not configured'];
        }

        $prompt = trim(mb_substr($prompt, 0, 500));
        if ($prompt === '') {
            return ['success' => false, 'error' => 'Empty prompt'];
        }

        // Build instruction — keep same format you already parse
        $instruction = $this->buildPrompt($prompt);

        try {
            $payload = [
                'model' => $this->modelId,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful technical content writer. Provide a short forum post.'],
                    ['role' => 'user', 'content' => $instruction]
                ],
                'max_tokens' => 600,
                'temperature' => 0.7
            ];

            $response = $this->httpClient->request('POST', self::GROQ_BASE, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => self::TIMEOUT,
            ]);

            // read body even on 4xx/5xx
            $data = $response->toArray(false);

            if (isset($data['error'])) {
                $this->logger->warning('Groq error', ['error' => $data['error']]);
                return ['success' => false, 'error' => 'AI service returned an error: ' . (string) $data['error']];
            }

            // OpenAI-compatible response: choices[0].message.content
            $generated = '';
            if (!empty($data['choices'][0]['message']['content'])) {
                $generated = (string) $data['choices'][0]['message']['content'];
            } elseif (!empty($data['choices'][0]['text'])) {
                // some models/compatibility modes
                $generated = (string) $data['choices'][0]['text'];
            } else {
                $this->logger->warning('Unexpected Groq response', ['raw' => $data]);
                return ['success' => false, 'error' => 'Unexpected AI response format'];
            }

            // Parse the generated text to title/content/tags using your existing parser
            $parsed = $this->parseAIResponse($generated, $prompt);

            // Run moderation on generated content (title + content) — important
            $moderation = $this->moderationService->moderatePost($parsed['title'], $parsed['content']);
            if (!$moderation['safe']) {
                $this->logger->warning('Generated content flagged by moderation', [
                    'moderation' => $moderation
                ]);
                return ['success' => false, 'error' => 'Generated content violates policy'];
            }

            // sanitize and limit sizes
            $title = mb_substr(strip_tags(trim($parsed['title'])), 0, 255);
            $content = mb_substr($this->cleanContent($parsed['content']), 0, 10000);
            $tags = array_values(array_slice($parsed['tags'] ?? [], 0, 5));

            return [
                'success' => true,
                'title' => $title,
                'content' => $content,
                'tags' => $tags
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Groq generation exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'AI generation failed: ' . $e->getMessage()];
        }
    }

    // ---------- helper methods (reuse your existing prompt + parser) ----------

    private function buildPrompt(string $userPrompt): string
    {
        return <<<PROMPT
You are a helpful technical content writer. Create a community forum post about: "{$userPrompt}"

Format your response EXACTLY like this:

TITLE: [Write a clear, engaging title]

CONTENT:
[Write 2-3 short paragraphs (each 2-4 sentences) of helpful, informative content about this topic. Keep language neutral and friendly. Do not include profanity or threats.]

TAGS: [tag1, tag2, tag3]

Now generate the post:
PROMPT;
    }

    // Copy/adapt your parseAIResponse and cleanContent logic here
    private function parseAIResponse(string $response, string $fallbackTopic): array
    {
        // simple reuse of previously used parsing strategy (title/content/tags)
        $response = str_replace(["\r\n", "\r"], "\n", $response);

        // (title extraction)
        if (preg_match('/TITLE:\s*(.+?)(?:\n(?:CONTENT:|\n))/is', $response, $m)) {
            $title = trim($m[1]);
        } elseif (preg_match('/^TITLE:\s*(.+)$/im', $response, $m2)) {
            $title = trim($m2[1]);
        } else {
            $firstLine = strtok($response, "\n");
            $title = $firstLine ? trim($firstLine) : ucfirst($fallbackTopic);
        }

        // (content)
        if (preg_match('/CONTENT:\s*(.+?)(?:\n\nTAGS:|$)/is', $response, $c)) {
            $content = trim($c[1]);
        } else {
            $afterTitle = preg_replace('/^.*?TITLE:.*?$/is', '', $response, 1);
            $content = trim($afterTitle);
            if ($content === '') {
                $content = "Here are some thoughts about {$fallbackTopic}.";
            }
        }

        // (tags)
        $tags = [];
        if (preg_match('/TAGS:\s*(.+)$/im', $response, $t)) {
            $tagsString = trim($t[1]);
            $tags = $this->extractTags($tagsString, $fallbackTopic);
        } else {
            $tags = $this->extractTags('', $fallbackTopic);
        }

        $content = $this->cleanContent($content);

        return ['title' => $title, 'content' => $content, 'tags' => $tags];
    }

    private function extractTags(string $tagsString, string $fallbackTopic): array
    {
        if ($tagsString !== '') {
            $tagsString = trim($tagsString, "[] \t\n\r");
            $parts = preg_split('/[,;]+/', $tagsString);
            $parts = array_map(fn($v) => strtolower(trim($v)), $parts);
            $parts = array_filter($parts);
            return array_slice(array_unique($parts), 0, 5);
        }

        $words = preg_split('/\W+/', strtolower($fallbackTopic));
        $words = array_filter($words, fn($w) => strlen($w) > 2);
        return array_slice(array_unique($words), 0, 3);
    }

    private function cleanContent(string $content): string
    {
        $content = preg_replace('/^(TITLE:|CONTENT:|TAGS:).*$/im', '', $content);
        $content = trim($content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = preg_replace('/[\x00-\x1F\x7F]/u', '', $content);
        return $content;
    }

    /**
     * Generate a short helpful reply for a given post (title + content).
     *
     * Returns:
     *  [
     *    'success' => bool,
     *    'content' => string,    // the reply text
     *    'error' => string|null
     *  ]
     */
    public function generateReply(string $postTitle, string $postContent): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'AI service not configured'];
        }

        // Build a compact instruction for reply generation
        $instruction = <<<PROMPT
You are a helpful, concise community member. Read the post below and write a short, friendly, and practical reply (2-3 sentences). Be respectful and avoid profanity or threats.

POST TITLE: {$postTitle}

POST CONTENT:
{$postContent}

Write a reply (2-3 sentences):
PROMPT;

        try {
            $payload = [
                'model' => $this->modelId,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful community member. Keep replies short and polite.'],
                    ['role' => 'user', 'content' => $instruction]
                ],
                'max_tokens' => 150,
                'temperature' => 0.6
            ];

            $response = $this->httpClient->request('POST', self::GROQ_BASE, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            if (isset($data['error'])) {
                $this->logger->warning('Groq reply error', ['error' => $data['error']]);
                // Provide a friendly error message for the frontend
                return ['success' => false, 'error' => $data['error']['message'] ?? 'AI service error'];
            }

            // OpenAI-compatible parsing
            $generated = '';
            if (!empty($data['choices'][0]['message']['content'])) {
                $generated = (string) $data['choices'][0]['message']['content'];
            } elseif (!empty($data['choices'][0]['text'])) {
                $generated = (string) $data['choices'][0]['text'];
            } else {
                $this->logger->warning('Unexpected Groq reply shape', ['raw' => $data]);
                return ['success' => false, 'error' => 'Unexpected AI response format'];
            }

            // Clean up generated reply text
            $replyText = $this->cleanContent($generated);
            $replyText = trim($replyText);
            // Limit reply length for safety
            if (mb_strlen($replyText) > 1000) {
                $replyText = mb_substr($replyText, 0, 1000);
            }

            // Run moderation to ensure generated reply is safe
            $moderation = $this->moderationService->moderateContent($replyText);
            if (!$moderation['safe']) {
                $this->logger->warning('Generated reply blocked', [
                    'scores' => $moderation['scores'],
                    'categories' => $moderation['categories']
                ]);
                return ['success' => false, 'error' => 'Generated reply violates content policy.'];
            }

            return ['success' => true, 'content' => $replyText];

        } catch (\Throwable $e) {
            $this->logger->error('Groq reply generation exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'AI generation failed: ' . $e->getMessage()];
        }
    }

public function generateRaw(string $prompt): string
{
    $response = $this->httpClient->request('POST', self::GROQ_BASE, [
        'headers' => [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => $this->modelId,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3,
        ],
        'timeout' => self::TIMEOUT,
    ]);

    $data = $response->toArray(false);

    if (!isset($data['choices'][0]['message']['content'])) {
        throw new \RuntimeException('Invalid AI response');
    }

    return $data['choices'][0]['message']['content'];
}
}