<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\PlagiarismPair;
use App\Entity\PlagiarismRun;
use App\Service\PlagiarismDetectorPro;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/plagiarism')]
#[IsGranted('ROLE_ADMIN')]
class PlagiarismAdminController extends AbstractController
{
    #[Route('/evaluation/{id}', name: 'admin_plagiarism_dashboard', methods: ['GET'])]
    public function dashboard(Evaluation $evaluation, EntityManagerInterface $em): Response
    {
        // ✅ only EXAM
        if (strtoupper((string) $evaluation->getType()) !== 'EXAM') {
            $this->addFlash('danger', 'Plagiat: uniquement pour EXAM.');
            return $this->redirectToRoute('evaluation_index');
        }

        // 1) runs + latest
        $runs = $em->getRepository(PlagiarismRun::class)->findBy(
            ['evaluation' => $evaluation],
            ['createdAt' => 'DESC']
        );

        $latestRun = $runs[0] ?? null;
        $pairs = [];

        if ($latestRun) {
            $pairs = $em->getRepository(PlagiarismPair::class)->findBy(
                ['run' => $latestRun],
                ['plagiarismPercent' => 'DESC']
            );
        }

        // 2) leaderboard: max % par étudiant (basé sur pairs)
        $leaderboard = [];
        foreach ($pairs as $p) {
            $ansA = $p->getAnswerA();
            $ansB = $p->getAnswerB();
            if (!$ansA || !$ansB) continue;

            $a = $ansA->getStudent();
            $b = $ansB->getStudent();
            if (!$a || !$b) continue;

            $aid = $a->getId();
            $bid = $b->getId();
            $score = $p->getPlagiarismPercent();

            $leaderboard[$aid] = max($leaderboard[$aid] ?? 0, $score);
            $leaderboard[$bid] = max($leaderboard[$bid] ?? 0, $score);
        }
        arsort($leaderboard);

        // 3) ✅ Submissions list (même si 1 étudiant) : IA/Web/Paste/Tab
        $submissions = $em->createQueryBuilder()
            ->select('a', 'q', 's')
            ->from(Answer::class, 'a')
            ->join('a.question', 'q')
            ->leftJoin('a.student', 's')
            ->where('q.evaluation = :e')
            ->andWhere('a.role = :role')
            ->setParameter('e', $evaluation)
            ->setParameter('role', 'SUBMISSION')
            ->orderBy('a.aiSuspicionPercent', 'DESC')
            ->addOrderBy('a.webPlagiarismPercent', 'DESC')
            ->addOrderBy('a.pasteCount', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('pages/admin/plagiarism/dashboard.html.twig', [
            'evaluation' => $evaluation,
            'runs' => $runs,
            'latestRun' => $latestRun,
            'pairs' => $pairs,
            'leaderboard' => $leaderboard,
            'submissions' => $submissions, // ✅ IMPORTANT
        ]);
    }

    #[Route('/evaluation/{id}/run', name: 'admin_plagiarism_run', methods: ['POST'])]
    public function run(
        Evaluation $evaluation,
        PlagiarismDetectorPro $detector,
        Request $request
    ): Response {
        if (!$this->isCsrfTokenValid('plagiarism_run_'.$evaluation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        if (strtoupper((string) $evaluation->getType()) !== 'EXAM') {
            $this->addFlash('danger', 'Plagiat: uniquement pour EXAM.');
            return $this->redirectToRoute('evaluation_index');
        }

        $detector->run($evaluation);
        $this->addFlash('success', 'Analyse plagiat (pro) terminée ✅');

        return $this->redirectToRoute('admin_plagiarism_dashboard', ['id' => $evaluation->getId()]);
    }
}