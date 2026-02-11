<?php

namespace App\Controller;

use App\Entity\Answer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/answers')]
#[IsGranted('ROLE_ADMIN')]
class AnswerController extends AbstractController
{
    #[Route('/', name: 'admin_answer_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $answers = $em->getRepository(Answer::class)->findBy([], ['id' => 'DESC']);

        $grouped = [];

        foreach ($answers as $answer) {
            $question = $answer->getQuestion();
            if (!$question) {
                continue;
            }

            $evaluation = $question->getEvaluation();
            if (!$evaluation) {
                continue;
            }

            $evalId = $evaluation->getId();

            if (!isset($grouped[$evalId])) {
                $grouped[$evalId] = [
                    'evaluation' => $evaluation,
                    'answers' => [],
                    'correct' => 0,
                    'incorrect' => 0,
                ];
            }

            if ($answer->getIsCorrect() === true) {
                $grouped[$evalId]['correct']++;
            } elseif ($answer->getIsCorrect() === false) {
                $grouped[$evalId]['incorrect']++;
            }

            $grouped[$evalId]['answers'][] = $answer;
        }

        krsort($grouped);

        return $this->render('pages/admin/answers/index.html.twig', [
            'grouped' => $grouped,
        ]);
    }

    #[Route('/{id}', name: 'admin_answer_show', methods: ['GET'])]
    public function show(Answer $answer): Response
    {
        return $this->render('pages/admin/answers/show.html.twig', [
            'answer' => $answer,
        ]);
    }
}
