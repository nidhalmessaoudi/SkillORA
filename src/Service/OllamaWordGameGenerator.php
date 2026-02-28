<?php

namespace App\Service;

class OllamaWordGameGenerator
{
    public function __construct(private OllamaClient $ollama) {}

    /**
     * @return array{title:string,theme:string,letters:string,words:list<string>}
     */
    public function generate(string $theme = 'symfony', string $level = 'easy'): array
    {
        $theme = trim($theme) ?: 'programming';
        $level = in_array($level, ['easy','medium'], true) ? $level : 'easy';

        $prompt = <<<PROMPT
You create an educational WORD CONNECT game about: {$theme}.
Difficulty: {$level}.

Return ONLY valid JSON. No text before/after.

Format:
{
  "title": "short title",
  "theme": "{$theme}",
  "letters": "7-9 uppercase letters",
  "words": ["WORD1","WORD2","WORD3","..."]
}

Rules:
- letters: uppercase A-Z, 7 to 9 chars
- words: 10 to 18 words
- each word length 3 to 10
- each word must be buildable using ONLY the letters (reuse letters allowed)
- include some educational words related to the theme (e.g. SYMFONY, ROUTE, CONTROLLER, PYTHON, FUNCTION...)
PROMPT;

        // IMPORTANT: chez toi le modèle est "mistral:latest"
        $raw = $this->ollama->generate('mistral:latest', $prompt);

        $json = $this->extractJson($raw);
        $data = json_decode($json, true);

        if (!is_array($data) || empty($data['letters']) || empty($data['words'])) {
            // fallback safe game (no crash)
            return [
                'title' => 'Word Connect',
                'theme' => $theme,
                'letters' => 'SYMFONY',
                'words' => ['SYMFONY','ROUTE','FORM','VIEW','CACHE','EVENT','DEBUG','TOKEN'],
            ];
        }

        $letters = preg_replace('/[^A-Z]/', '', (string) $data['letters']) ?? '';
        $data['letters'] = strtoupper($letters);
        if (strlen($data['letters']) < 7) $data['letters'] = 'SYMFONY';

        // normalize words
        $words = [];
        foreach ((array)$data['words'] as $w) {
            $w = strtoupper(trim((string)$w));
            $w = preg_replace('/[^A-Z]/', '', $w) ?? '';
            if (strlen($w) >= 3 && strlen($w) <= 10) $words[] = $w;
        }
        $words = array_values(array_unique($words));

        if (count($words) < 6) {
            $words = ['SYMFONY','ROUTE','FORM','VIEW','CACHE','EVENT','DEBUG','TOKEN'];
        }

        return [
            'title' => (string)($data['title'] ?? 'Word Connect'),
            'theme' => (string)($data['theme'] ?? $theme),
            'letters' => $data['letters'],
            'words' => $words,
        ];
    }

    private function extractJson(string $text): string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false) return '{}';
        return substr($text, $start, $end - $start + 1);
    }
}
