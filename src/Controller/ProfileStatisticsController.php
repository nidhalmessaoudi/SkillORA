<?php

namespace App\Controller;

use App\Entity\Evaluation;
use App\Repository\AnswerRepository;
use App\Repository\EvaluationRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfileStatisticsController extends AbstractController
{
    #[Route('/profile/statistics', name: 'profile_statistics', methods: ['GET'])]
    public function statisticsList(EvaluationRepository $evaluationRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        // Simple: show all evaluations (you can filter later)
        $evaluations = $evaluationRepository->findBy([], ['id' => 'DESC']);

        return $this->render('profile/statistics_list.html.twig', [
            'evaluations' => $evaluations,
        ]);
    }

    #[Route('/profile/statistics/pdf/{id}', name: 'profile_statistics_pdf', methods: ['GET'])]
    public function statisticsPdf(
        Evaluation $evaluation,
        AnswerRepository $answerRepository
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $stats = $answerRepository->getEvaluationStatsForUser($user, $evaluation);

        $html = $this->renderView('profile/statistics_pdf.html.twig', [
            'user' => $user,
            'evaluation' => $evaluation,
            'stats' => $stats,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('statistics-evaluation-%d.pdf', $evaluation->getId());

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]
        );
    }
}
