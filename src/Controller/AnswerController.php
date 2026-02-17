<?php

namespace App\Controller;

use App\Entity\Answer;
<<<<<<< Updated upstream
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
=======
use App\Entity\Evaluation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
>>>>>>> Stashed changes
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/answers')]
#[IsGranted('ROLE_ADMIN')]
class AnswerController extends AbstractController
{
    #[Route('/', name: 'admin_answer_index', methods: ['GET'])]
<<<<<<< Updated upstream
    public function index(EntityManagerInterface $em): Response
    {
        $answers = $em->getRepository(Answer::class)->findBy([], ['id' => 'DESC']);

=======
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $evaluationId = $request->query->getInt('evaluationId');
        $back = $request->query->get('back');

        // If no evaluationId => show all answers grouped by evaluation (fallback)
        if ($evaluationId <= 0) {
            $answers = $em->getRepository(Answer::class)->findBy([], ['id' => 'DESC']);

            return $this->render('pages/admin/answers/index.html.twig', [
                'selectedEvaluation' => null,
                'groupedByQuestion' => [],
                'correct' => 0,
                'incorrect' => 0,
                'back' => $back,
                'fallbackGrouped' => $this->groupAnswersByEvaluation($answers),
            ]);
        }

        /** @var Evaluation|null $evaluation */
        $evaluation = $em->getRepository(Evaluation::class)->find($evaluationId);
        if (!$evaluation) {
            $this->addFlash('danger', 'Evaluation not found.');
            return $this->redirectToRoute('evaluation_index');
        }

        // Only CHOICE answers of questions of this evaluation
        $answers = $em->createQueryBuilder()
            ->select('a', 'q', 'e')
            ->from(Answer::class, 'a')
            ->join('a.question', 'q')
            ->join('q.evaluation', 'e')
            ->where('e.id = :eid')
            ->andWhere('a.role = :role')
            ->setParameter('eid', $evaluationId)
            ->setParameter('role', 'CHOICE')
            ->orderBy('q.id', 'DESC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();

        $groupedByQuestion = [];
        $correct = 0;
        $incorrect = 0;

        foreach ($answers as $a) {
            $q = $a->getQuestion();
            if (!$q) continue;

            $qid = $q->getId();
            if (!isset($groupedByQuestion[$qid])) {
                $groupedByQuestion[$qid] = [
                    'question' => $q,
                    'answers' => [],
                ];
            }

            if ($a->getIsCorrect() === true) $correct++;
            if ($a->getIsCorrect() === false) $incorrect++;

            $groupedByQuestion[$qid]['answers'][] = $a;
        }

        krsort($groupedByQuestion);

        return $this->render('pages/admin/answers/index.html.twig', [
            'selectedEvaluation' => $evaluation,
            'groupedByQuestion' => $groupedByQuestion,
            'correct' => $correct,
            'incorrect' => $incorrect,
            'back' => $back,
            'fallbackGrouped' => null,
        ]);
    }

    private function groupAnswersByEvaluation(array $answers): array
    {
>>>>>>> Stashed changes
        $grouped = [];

        foreach ($answers as $answer) {
            $question = $answer->getQuestion();
<<<<<<< Updated upstream
            if (!$question) {
                continue;
            }

            $evaluation = $question->getEvaluation();
            if (!$evaluation) {
                continue;
            }
=======
            if (!$question) continue;

            $evaluation = $question->getEvaluation();
            if (!$evaluation) continue;
>>>>>>> Stashed changes

            $evalId = $evaluation->getId();

            if (!isset($grouped[$evalId])) {
                $grouped[$evalId] = [
                    'evaluation' => $evaluation,
                    'answers' => [],
<<<<<<< Updated upstream
                    'correct' => 0,
                    'incorrect' => 0,
                ];
            }

            if ($answer->getIsCorrect() === true) {
                $grouped[$evalId]['correct']++;
            } elseif ($answer->getIsCorrect() === false) {
                $grouped[$evalId]['incorrect']++;
            }

=======
                ];
            }

>>>>>>> Stashed changes
            $grouped[$evalId]['answers'][] = $answer;
        }

        krsort($grouped);
<<<<<<< Updated upstream

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
=======
        return $grouped;
>>>>>>> Stashed changes
    }
}
