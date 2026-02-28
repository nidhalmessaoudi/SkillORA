<?php

namespace App\Controller;

use App\Entity\Evaluation;
use App\Entity\EvaluationAudioAttempt;
use App\Entity\Question;
use App\Entity\QuestionAudioAttempt;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tts')]
#[IsGranted('ROLE_USER')]
class AudioTtsController extends AbstractController
{
    // QUIZ: 2 plays per question
    #[Route('/question/{id}/play', name: 'tts_question_play', methods: ['POST'])]
    public function playQuestion(
        Question $question,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], 401);
        }

        $max = 2;

        $attempt = $em->getRepository(QuestionAudioAttempt::class)->findOneBy([
            'user' => $user,
            'question' => $question,
        ]);

        if (!$attempt) {
            $attempt = new QuestionAudioAttempt();
            $attempt->setUser($user);
            $attempt->setQuestion($question);
            $em->persist($attempt);
        }

        if ($attempt->getPlayCount() >= $max) {
            return $this->json([
                'ok' => false,
                'reason' => 'limit',
                'remaining' => 0,
                'max' => $max,
            ], 429);
        }

        $attempt->inc();
        $em->flush();

        return $this->json([
            'ok' => true,
            'remaining' => $max - $attempt->getPlayCount(),
            'max' => $max,
        ]);
    }

    // EXAM: 4 plays per whole evaluation
    #[Route('/evaluation/{id}/play', name: 'tts_evaluation_play', methods: ['POST'])]
    public function playEvaluation(
        Evaluation $evaluation,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], 401);
        }

        // Only allow for EXAM (optional safety)
        if (strtoupper((string)$evaluation->getType()) !== 'EXAM') {
            return $this->json(['ok' => false, 'reason' => 'not_exam'], 400);
        }

        $max = 4;

        $attempt = $em->getRepository(EvaluationAudioAttempt::class)->findOneBy([
            'user' => $user,
            'evaluation' => $evaluation,
        ]);

        if (!$attempt) {
            $attempt = new EvaluationAudioAttempt();
            $attempt->setUser($user);
            $attempt->setEvaluation($evaluation);
            $em->persist($attempt);
        }

        if ($attempt->getPlayCount() >= $max) {
            return $this->json([
                'ok' => false,
                'reason' => 'limit',
                'remaining' => 0,
                'max' => $max,
            ], 429);
        }

        $attempt->inc();
        $em->flush();

        return $this->json([
            'ok' => true,
            'remaining' => $max - $attempt->getPlayCount(),
            'max' => $max,
        ]);
    }
}
