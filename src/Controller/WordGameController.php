<?php

namespace App\Controller;

use App\Entity\WordGame;
use App\Entity\WordGameProgress;
use App\Entity\WordGameWord;
use App\Service\OllamaWordGameGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/games')]
#[IsGranted('ROLE_USER')]
class WordGameController extends AbstractController
{
    #[Route('/', name: 'user_games_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $games = $em->getRepository(WordGame::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('games/index.html.twig', [
            'games' => $games,
        ]);
    }

    #[Route('/create', name: 'user_games_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, OllamaWordGameGenerator $gen): Response
    {
        $theme = (string) $request->request->get('theme', 'symfony');
        $level = (string) $request->request->get('level', 'easy');

        $payload = $gen->generate($theme, $level);

        $game = new WordGame();
        $game->setTitle($payload['title'] ?? 'Word Connect');
        $game->setTheme($payload['theme'] ?? $theme);
        $game->setLetters($payload['letters'] ?? 'SYMFONY');

        $words = (array) ($payload['words'] ?? []);
        foreach ($words as $w) {
            $w = strtoupper(trim((string) $w));
            if (strlen($w) < 3 || strlen($w) > 10) continue;

            $word = new WordGameWord();
            $word->setWord($w);
            $word->setPoints(strlen($w)); // scoring simple
            $game->addWord($word);
            $em->persist($word);
        }

        $em->persist($game);
        $em->flush();

        return $this->redirectToRoute('user_games_play', ['id' => $game->getId()]);
    }

    #[Route('/{id}/play', name: 'user_games_play', methods: ['GET'])]
    public function play(WordGame $game, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $progress = $em->getRepository(WordGameProgress::class)->findOneBy([
            'user' => $user,
            'game' => $game
        ]);

        if (!$progress) {
            $progress = new WordGameProgress();
            $progress->setUser($user);
            $progress->setGame($game);
            $progress->setFoundWords([]);
            $progress->setScore(0);
            $em->persist($progress);
            $em->flush();
        }

        $allWords = $em->getRepository(WordGameWord::class)->findBy(['game' => $game], ['points' => 'DESC']);

        return $this->render('games/play.html.twig', [
            'game' => $game,
            'progress' => $progress,
            'allWords' => $allWords,
            'found' => $progress->getFoundWords(),
            'score' => $progress->getScore(),
        ]);
    }

    #[Route('/{id}/check', name: 'user_games_check', methods: ['POST'])]
    public function check(WordGame $game, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $payload = $request->toArray();
        $word = strtoupper(trim((string)($payload['word'] ?? '')));

        if (strlen($word) < 3) {
            return $this->json(['ok' => false, 'reason' => 'too_short'], 400);
        }

        $progress = $em->getRepository(WordGameProgress::class)->findOneBy(['user' => $user, 'game' => $game]);
        if (!$progress) return $this->json(['ok' => false], 404);

        $found = $progress->getFoundWords();
        if (in_array($word, $found, true)) {
            return $this->json(['ok' => false, 'reason' => 'already_found', 'score' => $progress->getScore()], 409);
        }

        $valid = $em->getRepository(WordGameWord::class)->findOneBy(['game' => $game, 'word' => $word]);
        if (!$valid) {
            return $this->json(['ok' => false, 'reason' => 'not_in_list'], 422);
        }

        $found[] = $word;
        $progress->setFoundWords($found);

        $newScore = $progress->getScore() + $valid->getPoints();
        $progress->setScore($newScore);

        $em->flush();

        $totalWords = $em->getRepository(WordGameWord::class)->count(['game' => $game]);
        $foundCount = count($found);
        $won = ($totalWords > 0 && $foundCount >= $totalWords);

        return $this->json([
            'ok' => true,
            'points' => $valid->getPoints(),
            'score' => $newScore,
            'foundCount' => $foundCount,
            'totalWords' => $totalWords,
            'won' => $won,
        ]);
    }
}