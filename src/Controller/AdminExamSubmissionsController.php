<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\UserEvaluation;
use App\Service\GroqAiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Entity\Evaluation;

#[Route('/admin/exam/submissions')]
#[IsGranted('ROLE_ADMIN')]
class AdminExamSubmissionsController extends AbstractController
{
    #[Route('/', name: 'admin_exam_submissions', methods: ['GET'])]
    public function list(EntityManagerInterface $em): Response
    {
        // toutes les copies EXAM soumises
        $rows = $em->createQueryBuilder()
            ->select('ue, e, u')
            ->from(UserEvaluation::class, 'ue')
            ->join('ue.evaluation', 'e')
            ->join('ue.user', 'u')
            ->where('e.type = :type')
            ->andWhere('ue.submittedAt IS NOT NULL')
            ->setParameter('type', 'EXAM')
            ->orderBy('ue.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('pages/admin/exams/submissions.html.twig', [
            'rows' => $rows,
        ]);
    }

    #[Route('/{id}/ai-correct', name: 'admin_exam_ai_correct', methods: ['POST'])]
    public function aiCorrect(
        UserEvaluation $userEvaluation,
        Request $request,
        EntityManagerInterface $em,
        GroqAiClient $ai
    ): Response {
        if (!$this->isCsrfTokenValid('ai_correct_'.$userEvaluation->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $evaluation = $userEvaluation->getEvaluation();
        if (strtoupper((string)$evaluation->getType()) !== 'EXAM') {
            throw $this->createNotFoundException('Not an exam');
        }

        // récupérer la réponse EXAM (Answer SUBMISSION sur la première question/placeholder)
        $questions = $evaluation->getQuestions();
        $firstQuestion = $questions->first() ?: null;
        if (!$firstQuestion) {
            throw new \RuntimeException('Exam has no questions (placeholder missing)');
        }

        $student = $userEvaluation->getUser();

        $examAnswer = $em->getRepository(Answer::class)->findOneBy([
            'student' => $student,
            'question' => $firstQuestion,
            'role' => 'SUBMISSION',
        ]);

        if (!$examAnswer) {
            $this->addFlash('error', 'Aucune réponse trouvée pour cet étudiant.');
            return $this->redirectToRoute('admin_exam_submissions');
        }

        // Texte questions (on ignore le placeholder si tu veux)
        $questionsText = '';
        $i = 1;
        foreach ($questions as $q) {
            if ($q->getContent() === 'EXAM_SUBMISSION_PLACEHOLDER') continue;
            $questionsText .= "Q{$i}: ".$q->getContent()." (score: ".$q->getScore().")\n";
            $i++;
        }

        $result = $ai->gradeExam(
            $evaluation->getTitle() ?? 'EXAM',
            (int)$evaluation->getTotalScore(),
            $questionsText ?: '(questions non disponibles)',
            $examAnswer->getContent()
        );

        $userEvaluation->setScore($result['score']);
        $userEvaluation->setAiFeedback($result['feedback']);
        $userEvaluation->setIsCorrected(true);
        $userEvaluation->setAiCorrectedAt(new \DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', 'Correction IA terminée ✅');
        return $this->redirectToRoute('admin_exam_submissions');
    }

    #[Route('/{id}', name: 'admin_exam_submission_show', methods: ['GET'])]
public function show(
    UserEvaluation $userEvaluation,
    EntityManagerInterface $em
): Response {
    $evaluation = $userEvaluation->getEvaluation();
    if (strtoupper((string)$evaluation->getType()) !== 'EXAM') {
        throw $this->createNotFoundException('Not an exam');
    }

    $questions = $evaluation->getQuestions();
    $firstQuestion = $questions->first() ?: null;

    $examAnswer = null;
    if ($firstQuestion) {
        $examAnswer = $em->getRepository(Answer::class)->findOneBy([
            'student' => $userEvaluation->getUser(),
            'question' => $firstQuestion,
            'role' => 'SUBMISSION',
        ]);
    }

    return $this->render('pages/admin/exams/submission_show.html.twig', [
        'ue' => $userEvaluation,
        'evaluation' => $evaluation,
        'examAnswer' => $examAnswer,
    ]);
}


#[Route('/evaluation/{evaluationId}', name: 'admin_exam_submissions_by_exam', methods: ['GET'])]
public function listByExam(int $evaluationId, EntityManagerInterface $em): Response
{
    $rows = $em->createQueryBuilder()
        ->select('ue, e, u')
        ->from(UserEvaluation::class, 'ue')
        ->join('ue.evaluation', 'e')
        ->join('ue.user', 'u')
        ->where('e.type = :type')
        ->andWhere('e.id = :eid')
        ->andWhere('ue.submittedAt IS NOT NULL')
        ->setParameter('type', 'EXAM')
        ->setParameter('eid', $evaluationId)
        ->orderBy('ue.submittedAt', 'DESC')
        ->getQuery()
        ->getResult();

    return $this->render('pages/admin/exams/submissions.html.twig', [
        'rows' => $rows,
    ]);
}


}