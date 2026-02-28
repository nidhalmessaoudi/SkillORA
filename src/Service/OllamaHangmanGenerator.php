<?php

namespace App\Service;

class OllamaHangmanGenerator
{
    public function __construct(private OllamaClient $ollama) {}

    /**
     * @return array{title:string,hint:string,answer:string}
     */
    public function generate(string $topic = 'symfony', string $level = 'easy'): array
    {
        $topic = trim($topic) ?: 'programming';
        $level = in_array($level, ['easy','medium','hard'], true) ? $level : 'easy';

        $prompt = <<<PROMPT
You generate educational HANGMAN content for programming students.

Topic: {$topic}
Difficulty: {$level}

Return ONLY valid JSON. No markdown. No extra text.

Format:
{
  "title": "short title",
  "hint": "one short sentence definition (EN or FR)",
  "answer": "ONE_WORD_UPPERCASE"
}

Rules:
- answer: ONE SINGLE WORD (no spaces, no dash), only A-Z, length 5 to 12
- hint: describes the answer clearly but does not contain the answer
- answer should be related to programming / {$topic} (examples: SYMFONY, CONTROLLER, ROUTER, PYTHON, FUNCTION, VARIABLE, DATABASE, DOCTRINE)
PROMPT;

        $raw = $this->ollama->generate('mistral:latest', $prompt);

        $json = $this->extractJson($raw);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return $this->fallback($topic);
        }

        $answer = strtoupper((string)($data['answer'] ?? ''));
        $answer = preg_replace('/[^A-Z]/', '', $answer) ?? '';

        if ($answer === '' || strlen($answer) < 5 || strlen($answer) > 12) {
            return $this->fallback($topic);
        }

        $hint = trim((string)($data['hint'] ?? ''));
        if ($hint === '') {
            $hint = 'Guess the secret word related to programming.';
        }

        return [
            'title' => (string)($data['title'] ?? 'Hangman'),
            'hint' => $hint,
            'answer' => $answer,
        ];
    }

    private function extractJson(string $text): string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false) return '{}';
        return substr($text, $start, $end - $start + 1);
    }

    /** @return array{title:string,hint:string,answer:string} */
    private function fallback(string $topic): array
    {
        // fallback simple par topic
        if (str_contains(strtolower($topic), 'python')) {
            return ['title' => 'Python Hangman', 'hint' => 'A popular programming language.', 'answer' => 'PYTHON'];
        }
        if (str_contains(strtolower($topic), 'database')) {
            return ['title' => 'DB Hangman', 'hint' => 'A structured collection of data.', 'answer' => 'DATABASE'];
        }
        return ['title' => 'Symfony Hangman', 'hint' => 'A PHP framework used for web development.', 'answer' => 'SYMFONY'];
    }
}
