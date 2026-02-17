<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\Question;
use App\Form\QuestionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/question')]
#[IsGranted('ROLE_ADMIN')]
class AdminQuestionController extends AbstractController
{
    #[Route('/new', name: 'admin_question_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $evaluationId = $request->query->getInt('evaluationId');
        $back = $request->query->get('back'); // URL de retour (optionnel)

        $evaluation = $em->getRepository(Evaluation::class)->find($evaluationId);
        if (!$evaluation) {
            throw $this->createNotFoundException('Evaluation not found');
        }

        $question = new Question();
        $question->setEvaluation($evaluation);

        // Si tu veux forcer EXAM uniquement:
        // if (strtoupper($evaluation->getType()) !== 'EXAM') throw $this->createNotFoundException();

        $form = $this->createForm(QuestionType::class, $question, [
            'evaluation_locked' => true, // ✅ lock le champ evaluation
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($question);

            // ✅ recalcul total score
            $evaluation->calculateTotalScore();

            $em->flush();

            $this->addFlash('success', 'Question ajoutée.');
            return $back
                ? $this->redirect($back)
                : $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        return $this->render('pages/admin/questions/form.html.twig', [
            'form' => $form->createView(),
            'evaluation' => $evaluation,
            'mode' => 'new',
            'back' => $back,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_question_edit', methods: ['GET', 'POST'])]
    public function edit(Question $question, Request $request, EntityManagerInterface $em): Response
    {
        $evaluation = $question->getEvaluation();
        $back = $request->query->get('back');

        $form = $this->createForm(QuestionType::class, $question, [
            'evaluation_locked' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evaluation->calculateTotalScore();
            $em->flush();

            $this->addFlash('success', 'Question modifiée.');
            return $back
                ? $this->redirect($back)
                : $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        return $this->render('pages/admin/questions/form.html.twig', [
            'form' => $form->createView(),
            'evaluation' => $evaluation,
            'mode' => 'edit',
            'back' => $back,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_question_delete', methods: ['POST'])]
    public function delete(Question $question, Request $request, EntityManagerInterface $em): Response
    {
        $back = $request->query->get('back');
        $token = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete_question_' . $question->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $evaluation = $question->getEvaluation();

        $em->remove($question);
        $evaluation->calculateTotalScore();
        $em->flush();

        $this->addFlash('success', 'Question supprimée.');

        return $back
            ? $this->redirect($back)
            : $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation?->getId()]);
    }
}
