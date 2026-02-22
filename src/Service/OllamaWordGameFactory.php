<?php

namespace App\Service;

use App\Entity\WordGame;
use App\Entity\WordGameWord;
use Doctrine\ORM\EntityManagerInterface;

class OllamaWordGameFactory
{
    public function __construct(
        private OllamaClient $ollama,
        private EntityManagerInterface $em
    ) {}

    public function createGame(string $theme = 'symfony', string $level = 'easy'): WordGame
    {
        $prompt = <<<PROMPT
Create an educational WORD GAME about: "{$theme}" for programming students.
Difficulty: {$level}.

Return ONLY valid JSON, no markdown:
{
  "title": "string",
  "theme": "string",
  "letters": "UPPERCASE_6_TO_8",
  "words": [
    {"word":"UPPERCASE","points":2},
    {"word":"UPPERCASE","points":1}
  ]
}

Rules:
- letters: 6 to 8 uppercase letters
- 6 to 12 words
- every word must be buildable from the letters (using each letter at most once)
- words must be related to programming / {$theme}
PROMPT;

        $raw = $this->ollama->generate('mistral:latest', $prompt);
        $data = json_decode($raw, true);

        if (!is_array($data) || empty($data['letters']) || empty($data['words'])) {
            return $this->fallbackGame($theme);
        }

        $letters = strtoupper(preg_replace('/[^A-Z]/', '', $data['letters']));
        if (strlen($letters) < 6 || strlen($letters) > 8) {
            return $this->fallbackGame($theme);
        }

        $game = new WordGame();
        $game->setTitle((string)($data['title'] ?? 'Word Game'));
        $game->setTheme((string)($data['theme'] ?? $theme));
        $game->setLetters($letters);

        $wordsAdded = 0;
        foreach ($data['words'] as $w) {
            if (!is_array($w) || empty($w['word'])) continue;
            $word = strtoupper(preg_replace('/[^A-Z]/', '', (string)$w['word']));
            $points = (int)($w['points'] ?? 1);
            if ($points < 1) $points = 1;

            if ($word === '' || strlen($word) < 2) continue;
            if (!$this->canBuild($letters, $word)) continue;

            $entity = new WordGameWord();
            $entity->setWord($word);
            $entity->setPoints($points);
            $game->addWord($entity);

            $wordsAdded++;
        }

        if ($wordsAdded < 6) {
            return $this->fallbackGame($theme);
        }

        $this->em->persist($game);
        $this->em->flush();

        return $game;
    }

    private function canBuild(string $letters, string $word): bool
    {
        $pool = str_split($letters);
        foreach (str_split($word) as $ch) {
            $i = array_search($ch, $pool, true);
            if ($i === false) return false;
            unset($pool[$i]);
        }
        return true;
    }

    private function fallbackGame(string $theme): WordGame
    {
        $game = new WordGame();
        $game->setTitle('Symfony Word Game');
        $game->setTheme($theme);
        $game->setLetters('SYMFONY');

        foreach ([
            ['SYMF', 2],
            ['SON', 1],
            ['MY', 1],
            ['FUN', 2],
            ['NO', 1],
            ['ON', 1],
        ] as [$w,$p]) {
            $wg = new WordGameWord();
            $wg->setWord($w)->setPoints($p);
            $game->addWord($wg);
        }

        $this->em->persist($game);
        $this->em->flush();

        return $game;
    }
}