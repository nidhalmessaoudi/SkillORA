<?php

namespace App\Service;

use App\Entity\Lesson;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LessonSummaryService
{
    private const API_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const FALLBACK_MODEL = 'llama-3.1-8b-instant';
    private const PRIMARY_TIMEOUT_SECONDS = 45;
    private const FALLBACK_TIMEOUT_SECONDS = 25;
    private const FALLBACK_INPUT_CHARS = 9_000;
    private const PDF_PRIMARY_INPUT_CHARS = 6_000;
    private const PDF_FALLBACK_INPUT_CHARS = 3_500;
    private const LONG_DOC_THRESHOLD_CHARS = 8_000;
    private const LONG_DOC_CHUNK_CHARS = 3_200;
    private const LONG_DOC_MAX_CHUNKS = 6;
    private const LONG_DOC_STEP_DELAY_US = 350_000;

    public function __construct(
        private readonly LessonContentExtractorService $contentExtractor,
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:GROQ_API_KEY)%')]
        private readonly string $groqApiKey,
        #[Autowire('%env(string:GROQ_MODEL)%')]
        private readonly string $groqModel,
    ) {
    }

    public function summarizeLesson(Lesson $lesson): string
    {
        if (trim($this->groqApiKey) === '') {
            throw new \RuntimeException('Missing GROQ_API_KEY configuration.');
        }

        $sourceText = $this->sanitizeText($this->contentExtractor->extractForSummary($lesson));
        if ($sourceText === '') {
            throw new \RuntimeException('No readable content found in this lesson.');
        }

        if (mb_strlen($sourceText) > self::LONG_DOC_THRESHOLD_CHARS) {
            return $this->summarizeLongDocument($lesson, $sourceText);
        }

        return $this->requestSummaryWithFallback($lesson, $sourceText);
    }

    private function summarizeLongDocument(Lesson $lesson, string $sourceText): string
    {
        $allChunks = $this->splitTextIntoChunks($sourceText, self::LONG_DOC_CHUNK_CHARS);
        $chunks = $this->selectRepresentativeChunks($allChunks, self::LONG_DOC_MAX_CHUNKS);

        if ($chunks === []) {
            throw new \RuntimeException('No readable content found for summarization.');
        }

        $partials = [];
        $count = count($chunks);

        foreach ($chunks as $index => $chunk) {
            try {
                $partials[] = $this->requestSummaryWithFallback($lesson, $chunk);
            } catch (\RuntimeException $e) {
                // Skip failed chunk requests; we only fail if every chunk fails.
                if (!$this->shouldFallback($e)) {
                    throw $e;
                }
            }

            if ($index < $count - 1) {
                usleep(self::LONG_DOC_STEP_DELAY_US);
            }
        }

        if ($partials === []) {
            throw new \RuntimeException('AI service is busy right now. Please wait 10-20 seconds and try again.');
        }

        if (count($partials) === 1) {
            return $partials[0];
        }

        $synthesisInput = "Combine these partial summaries into one coherent final summary.\n\n";
        foreach ($partials as $i => $partial) {
            $synthesisInput .= sprintf("Partial summary %d:\n%s\n\n", $i + 1, $partial);
        }

        return $this->requestSummaryWithFallback($lesson, $synthesisInput);
    }

    private function requestSummaryWithFallback(Lesson $lesson, string $sourceText): string
    {
        $isPdf = $lesson->getType() === 'pdf';

        // PDFs can be long/noisy. Start with lighter settings to avoid Groq free-tier saturation.
        $primaryModel = $isPdf ? self::FALLBACK_MODEL : $this->groqModel;
        $primaryInputChars = $isPdf ? self::PDF_PRIMARY_INPUT_CHARS : mb_strlen($sourceText);
        $primaryInput = mb_substr($sourceText, 0, $primaryInputChars);
        $primaryTimeout = $isPdf ? 22 : self::PRIMARY_TIMEOUT_SECONDS;
        $primaryTokens = $isPdf ? 480 : 700;

        try {
            return $this->requestSummary(
                $lesson,
                $primaryInput,
                $primaryModel,
                $primaryTimeout,
                $primaryTokens,
            );
        } catch (\RuntimeException $e) {
            if (!$this->shouldFallback($e)) {
                throw $e;
            }
        }

        // Small delay helps when provider is throttling burst requests.
        usleep(700_000);

        $fallbackInputChars = $isPdf ? self::PDF_FALLBACK_INPUT_CHARS : self::FALLBACK_INPUT_CHARS;
        $fallbackInput = mb_substr($sourceText, 0, $fallbackInputChars);

        try {
            return $this->requestSummary(
                $lesson,
                $fallbackInput,
                self::FALLBACK_MODEL,
                self::FALLBACK_TIMEOUT_SECONDS,
                $isPdf ? 420 : 520,
            );
        } catch (\RuntimeException) {
            throw new \RuntimeException('AI service is busy right now. Please wait 10-20 seconds and try again.');
        }
    }

    private function requestSummary(
        Lesson $lesson,
        string $sourceText,
        string $model,
        int $timeoutSeconds,
        int $maxTokens,
    ): string {
        $data = [];
        $prompt = sprintf(
            "Lesson title: %s\n\nLesson source content:\n%s",
            (string) ($lesson->getTitle() ?? 'Untitled lesson'),
            $sourceText,
        );

        try {
            $response = $this->httpClient->request('POST', self::API_ENDPOINT, [
                'timeout' => $timeoutSeconds,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an academic learning assistant. Write precise, factual, student-friendly summaries. Preserve key technical terms, avoid speculation, and keep language clear and concise.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt . "\n\nRespond in plain text using this exact format:\nSummary:\n(4-6 concise sentences)\n\nKey Points:\n- point 1\n- point 2\n- point 3\n- point 4\n\nImportant Terms:\n- term: short explanation\n- term: short explanation\n\nRules:\n- Keep output under 220 words.\n- Include only information supported by the lesson content.\n- Do not add extra sections.",
                        ],
                    ],
                ],
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($status >= 400) {
                $apiMessage = $data['error']['message'] ?? 'Groq request failed.';
                throw new \RuntimeException((string) $apiMessage);
            }
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('AI service timeout or network issue. Please try again.');
        }

        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('AI service returned an empty summary.');
        }

        return trim($content);
    }

    private function shouldFallback(\RuntimeException $e): bool
    {
        $message = mb_strtolower($e->getMessage());
        return str_contains($message, 'timeout')
            || str_contains($message, 'network')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, '503')
            || str_contains($message, 'busy');
    }

    private function sanitizeText(string $text): string
    {
        $clean = preg_replace('/[^\P{C}\n\t]+/u', ' ', $text) ?? $text;
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        return trim($clean);
    }

    /**
     * @return list<string>
     */
    private function splitTextIntoChunks(string $text, int $chunkSize): array
    {
        $chunks = [];
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; $offset += $chunkSize) {
            $piece = trim(mb_substr($text, $offset, $chunkSize));
            if ($piece !== '') {
                $chunks[] = $piece;
            }
        }

        return $chunks;
    }

    /**
     * @param list<string> $chunks
     * @return list<string>
     */
    private function selectRepresentativeChunks(array $chunks, int $maxChunks): array
    {
        $count = count($chunks);
        if ($count <= $maxChunks) {
            return $chunks;
        }

        $indices = [];
        for ($i = 0; $i < $maxChunks; $i++) {
            $indices[] = (int) round($i * ($count - 1) / max(1, $maxChunks - 1));
        }

        $indices = array_values(array_unique($indices));
        $selected = [];
        foreach ($indices as $index) {
            if (isset($chunks[$index])) {
                $selected[] = $chunks[$index];
            }
        }

        return $selected;
    }
}
