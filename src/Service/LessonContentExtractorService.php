<?php

namespace App\Service;

use App\Entity\Lesson;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LessonContentExtractorService
{
    private const MAX_PDF_BYTES = 25_000_000; // 25 MB safety limit
    private const MAX_SUMMARY_INPUT_CHARS = 18_000;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function extractForSummary(Lesson $lesson): string
    {
        $type = (string) ($lesson->getType() ?? '');

        if ($type === 'text') {
            $text = $this->extractTextLessonContent($lesson);
        } elseif ($type === 'pdf') {
            $text = $this->extractPdfLessonContent($lesson);
        } else {
            throw new \RuntimeException('AI summary is currently available for text and PDF lessons only.');
        }

        if ($text === '') {
            throw new \RuntimeException('No readable content found in this lesson.');
        }

        if (mb_strlen($text) > self::MAX_SUMMARY_INPUT_CHARS) {
            return mb_substr($text, 0, self::MAX_SUMMARY_INPUT_CHARS);
        }

        return $text;
    }

    private function extractTextLessonContent(Lesson $lesson): string
    {
        $raw = (string) ($lesson->getContent() ?? '');
        if (trim($raw) === '') {
            throw new \RuntimeException('This text lesson is empty.');
        }

        // Content is rich HTML from editor; convert to readable plain text.
        $plain = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->normalizeWhitespace($plain);
    }

    private function extractPdfLessonContent(Lesson $lesson): string
    {
        $filePath = (string) ($lesson->getFilePath() ?? '');
        if ($filePath === '') {
            throw new \RuntimeException('No PDF file found for this lesson.');
        }

        $absolutePath = $this->resolvePublicFilePath($filePath);
        if (!$absolutePath || !is_file($absolutePath)) {
            throw new \RuntimeException('PDF file is missing on server.');
        }

        $size = @filesize($absolutePath);
        if (is_int($size) && $size > self::MAX_PDF_BYTES) {
            throw new \RuntimeException('PDF is too large to summarize.');
        }

        try {
            $pdf = (new PdfParser())->parseFile($absolutePath);
            $text = $this->normalizeWhitespace((string) $pdf->getText());
        } catch (\Throwable) {
            throw new \RuntimeException('Unable to parse this PDF file.');
        }

        if ($text === '') {
            throw new \RuntimeException('Could not extract readable text from this PDF. Scanned/image PDFs are not supported.');
        }

        return $text;
    }

    private function resolvePublicFilePath(string $publicPath): ?string
    {
        if (!str_starts_with($publicPath, '/')) {
            return null;
        }

        $full = $this->projectDir . '/public' . $publicPath;
        $real = realpath($full);
        $publicRoot = realpath($this->projectDir . '/public');

        if ($real === false || $publicRoot === false) {
            return null;
        }

        // Prevent path traversal outside public dir.
        if (!str_starts_with($real, $publicRoot)) {
            return null;
        }

        return $real;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }
}
