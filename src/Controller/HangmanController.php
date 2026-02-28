<?php

namespace App\Controller;

use App\Entity\HangmanAttempt;
use App\Entity\HangmanGame;
use App\Entity\User;
use App\Service\OllamaHangmanGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/games/hangman')]
#[IsGranted('ROLE_USER')]
class HangmanController extends AbstractController
{
    #[Route('/', name: 'user_hangman_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $games = $em->getRepository(HangmanGame::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('hangman/index.html.twig', [
            'games' => $games
        ]);
    }

    #[Route('/create', name: 'user_hangman_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, OllamaHangmanGenerator $gen): Response
    {
        $topic = (string)$request->request->get('topic', 'symfony');
        $level = (string)$request->request->get('level', 'easy');

        $payload = $gen->generate($topic, $level);

        $game = (new HangmanGame())
            ->setTitle($payload['title'] ?? 'Hangman')
            ->setTopic($topic)
            ->setLevel($level)
            ->setHint($payload['hint'] ?? '')
            ->setAnswer($payload['answer'] ?? 'SYMFONY')
            ->setMaxMistakes(7);

        $em->persist($game);
        $em->flush();

        return $this->redirectToRoute('user_hangman_play', ['id' => $game->getId()]);
    }

    #[Route('/{id}/play', name: 'user_hangman_play', methods: ['GET'])]
    public function play(HangmanGame $game, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $attempt = $em->getRepository(HangmanAttempt::class)->findOneBy([
            'user' => $user,
            'game' => $game
        ]);

        if (!$attempt) {
            $attempt = (new HangmanAttempt())
                ->setUser($user)
                ->setGame($game)
                ->setGuessed([])
                ->setMistakes(0)
                ->setWon(false)
                ->setLost(false)
                ->setScore(0);

            $em->persist($attempt);
            $em->flush();
        }

        $masked = $this->mask($game->getAnswer(), $attempt->getGuessed());

        return $this->render('hangman/play.html.twig', [
            'game' => $game,
            'attempt' => $attempt,
            'masked' => $masked,
        ]);
    }

    #[Route('/{id}/guess', name: 'user_hangman_guess', methods: ['POST'])]
    public function guess(HangmanGame $game, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'reason' => 'no_user'], 403);
        }

        $attempt = $em->getRepository(HangmanAttempt::class)->findOneBy([
            'user' => $user,
            'game' => $game
        ]);

        if (!$attempt) return $this->json(['ok' => false], 404);

        if ($attempt->isWon() || $attempt->isLost()) {
            return $this->json(['ok' => false, 'reason' => 'finished'], 409);
        }

        $payload = $request->toArray();
        $letter = strtoupper((string)($payload['letter'] ?? ''));
        $letter = preg_replace('/[^A-Z]/', '', $letter);

        if (strlen($letter) !== 1) {
            return $this->json(['ok' => false, 'reason' => 'bad_letter'], 400);
        }

        $guessed = $attempt->getGuessed();
        if (in_array($letter, $guessed, true)) {
            return $this->json(['ok' => false, 'reason' => 'already'], 409);
        }

        $guessed[] = $letter;
        $attempt->setGuessed($guessed);

        $answer = $game->getAnswer();
        $hit = (strpos($answer, $letter) !== false);

        if (!$hit) {
            $attempt->setMistakes($attempt->getMistakes() + 1);
            $attempt->setScore(max(0, $attempt->getScore() - 1)); // petite pénalité
        } else {
            $attempt->setScore($attempt->getScore() + 2);
        }

        $masked = $this->mask($answer, $attempt->getGuessed());
        $won = ($masked === $answer);
        $lost = ($attempt->getMistakes() >= $game->getMaxMistakes());

        $attempt->setWon($won);
        $attempt->setLost($lost);
        $attempt->touch();

        $em->flush();

        return $this->json([
            'ok' => true,
            'hit' => $hit,
            'masked' => $masked,
            'guessed' => $attempt->getGuessed(),
            'mistakes' => $attempt->getMistakes(),
            'maxMistakes' => $game->getMaxMistakes(),
            'won' => $won,
            'lost' => $lost,
            'score' => $attempt->getScore(),
            'answer' => ($won || $lost) ? $answer : null
        ]);
    }

    /**
     * @param list<string> $guessed
     */
    private function mask(string $answer, array $guessed): string
    {
        $out = '';
        for ($i=0; $i<strlen($answer); $i++) {
            $ch = $answer[$i];
            $out .= in_array($ch, $guessed, true) ? $ch : '_';
        }
        return $out;
    }

    #[Route('/new', name: 'user_hangman_new', methods: ['POST'])]
public function newGame(Request $request, EntityManagerInterface $em, OllamaHangmanGenerator $gen): Response
{
    if (!$this->isCsrfTokenValid('hangman_new', (string)$request->request->get('_token'))) {
        throw $this->createAccessDeniedException('Invalid CSRF token');
    }

    $topic = (string)$request->request->get('topic', 'symfony');
    $level = (string)$request->request->get('level', 'easy');

    $payload = $gen->generate($topic, $level);

    $game = (new HangmanGame())
        ->setTitle($payload['title'] ?? 'Hangman')
        ->setTopic($topic)
        ->setLevel($level)
        ->setHint($payload['hint'] ?? '')
        ->setAnswer($payload['answer'] ?? 'SYMFONY')
        ->setMaxMistakes(8); // ✅ 8 étapes de dessin

    $em->persist($game);
    $em->flush();

    return $this->redirectToRoute('user_hangman_play', ['id' => $game->getId()]);
}
}
