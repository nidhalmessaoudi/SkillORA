<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WordGame;
use App\Entity\WordGameProgress;
use App\Service\OllamaWordGameFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/games')]
#[IsGranted('ROLE_USER')]
class UserGamesController extends AbstractController
{
    #[Route('/', name: 'user_games_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        // ✅ dernière partie la plus récente (tu peux filtrer par theme après)
        $game = $em->getRepository(WordGame::class)->findOneBy([], ['id' => 'DESC']);

        if (!$game) {
            // si aucun game dans DB
            return $this->render('games/empty.html.twig');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $progress = $em->getRepository(WordGameProgress::class)->findOneBy([
            'user' => $user,
            'game' => $game,
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

        $allWords = [];
        foreach ($game->getWords() as $w) {
            $allWords[] = ['word' => $w->getWord(), 'points' => $w->getPoints()];
        }

        return $this->render('games/index.html.twig', [
            'game' => $game,
            'allWords' => $allWords,
            'found' => $progress->getFoundWords(),
            'score' => $progress->getScore(),
        ]);
    }

    // ✅ Vérifier / valider un mot
    #[Route('/{id}/submit', name: 'user_games_submit', methods: ['POST'])]
    public function submit(
        WordGame $game,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'reason' => 'no_user'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        $word = strtoupper(trim((string)($payload['word'] ?? '')));
        $word = preg_replace('/[^A-Z]/', '', $word) ?? '';

        if ($word === '' || strlen($word) < 2) {
            return $this->json(['ok' => false, 'reason' => 'empty'], 400);
        }

        $progress = $em->getRepository(WordGameProgress::class)->findOneBy([
            'user' => $user,
            'game' => $game,
        ]);

        if (!$progress) {
            $progress = new WordGameProgress();
            $progress->setUser($user);
            $progress->setGame($game);
            $progress->setFoundWords([]);
            $progress->setScore(0);
            $em->persist($progress);
        }

        $found = $progress->getFoundWords();
        if (in_array($word, $found, true)) {
            return $this->json(['ok' => false, 'reason' => 'already_found']);
        }

        // check word exists in DB list
        $match = null;
        foreach ($game->getWords() as $w) {
            if ($w->getWord() === $word) {
                $match = $w;
                break;
            }
        }

        if (!$match) {
            return $this->json(['ok' => false, 'reason' => 'wrong']);
        }

        /** @var list<string> $found */
        $found[] = $word;
        $progress->setFoundWords($found);
        $progress->setScore($progress->getScore() + $match->getPoints());

        $em->flush();

        $total = count($game->getWords());
        $foundCount = count($found);

        return $this->json([
            'ok' => true,
            'word' => $word,
            'points' => $match->getPoints(),
            'score' => $progress->getScore(),
            'foundCount' => $foundCount,
            'total' => $total,
            'win' => ($foundCount >= $total),
        ]);
    }

    // ✅ Générer un nouveau jeu via Ollama
    #[Route('/generate', name: 'user_games_generate', methods: ['POST'])]
    public function generate(
        Request $request,
        OllamaWordGameFactory $factory
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $theme = (string)($payload['theme'] ?? 'symfony');
        $level = (string)($payload['level'] ?? 'easy');

        $game = $factory->createGame($theme, $level);

        return $this->json([
            'ok' => true,
            'redirect' => $this->generateUrl('user_games_index'),
            'gameId' => $game->getId(),
        ]);
    }
}
