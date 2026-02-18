<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\Question;
use App\Form\QuestionType;
use App\Service\ExamPdfUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/questions')]
#[IsGranted('ROLE_ADMIN')]
class QuestionController extends AbstractController
{
    #[Route('/', name: 'admin_question_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $questions = $em->getRepository(Question::class)->findBy([], ['id' => 'DESC']);

        $grouped = ['QUIZ' => [], 'EXAM' => []];

        foreach ($questions as $q) {
            $eval = $q->getEvaluation();
            if (!$eval) continue;

            $type = strtoupper((string) $eval->getType());
            if (!in_array($type, ['QUIZ', 'EXAM'], true)) $type = 'QUIZ';

            $evalId = $eval->getId();
            if (!isset($grouped[$type][$evalId])) {
                $grouped[$type][$evalId] = [
                    'evaluation' => $eval,
                    'items' => [],
                ];
            }
            $grouped[$type][$evalId]['items'][] = $q;
        }

        foreach ($grouped as $type => $evalGroups) {
            krsort($evalGroups);
            $grouped[$type] = $evalGroups;
        }

        return $this->render('pages/admin/questions/index.html.twig', [
            'questions' => $questions,
            'grouped' => $grouped,
        ]);
    }

    #[Route('/new', name: 'admin_question_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ExamPdfUpdater $pdfUpdater): Response
    {
        $question = new Question();
        $question->setScore(1);

        $evaluationId = $request->query->getInt('evaluationId');
        $evaluation = null;

        if ($evaluationId > 0) {
            $evaluation = $em->getRepository(Evaluation::class)->find($evaluationId);
            if ($evaluation) {
                $question->setEvaluation($evaluation);

                // ✅ EXAM => TEXT (exercice)
                if (strtoupper((string) $evaluation->getType()) === 'EXAM') {
                    $question->setType('TEXT');
                } else {
                    $question->setType('MCQ');
                }
            }
        } else {
            $question->setType('MCQ');
        }

        if ($question->getType() === 'MCQ') {
            $this->ensureDefaultChoices($question, 4);
        }

        $form = $this->createForm(QuestionType::class, $question, [
            'evaluation_locked' => (bool) $evaluationId,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ sécurité content
            $question->setContent(trim((string) $question->getContent()));
            if ($question->getContent() === '') {
                $this->addFlash('error', 'Veuillez saisir le contenu de la question/exercice.');
                return $this->render('pages/admin/questions/form.html.twig', [
                    'form' => $form->createView(),
                    'question' => $question,
                    'mode' => 'new',
                ]);
            }

            // TEXT => supprimer answers CHOICE
            if ($question->getType() === 'TEXT') {
                foreach ($question->getAnswers() as $a) {
                    if ($a->isChoice()) {
                        $em->remove($a);
                    }
                }
            }

            // MCQ => exactement 1 bonne réponse
            if ($question->getType() === 'MCQ') {
                $correctCount = 0;

                foreach ($question->getAnswers() as $a) {
                    $a->setRole('CHOICE');
                    $a->setStudent(null);
                    if ($a->getIsCorrect() === true) $correctCount++;
                }

                if ($correctCount !== 1) {
                    $this->addFlash('error', 'For MCQ, you must select exactly ONE correct answer.');
                    return $this->render('pages/admin/questions/form.html.twig', [
                        'form' => $form->createView(),
                        'question' => $question,
                        'mode' => 'new',
                    ]);
                }
            }

            $em->persist($question);

            if ($question->getEvaluation()) {
                $question->getEvaluation()->calculateTotalScore();
            }

            $em->flush();

            // ✅ Regénérer PDF si EXAM
            $eval = $question->getEvaluation();
            if ($eval && strtoupper((string) $eval->getType()) === 'EXAM') {
                $pdfUpdater->regeneratePdf($eval);
            }

            $this->addFlash('success', 'Question created successfully ✅');

            $back = $request->query->get('back');
            if ($back) return $this->redirect($back);

            return $this->redirectToRoute('admin_question_index');
        }

        return $this->render('pages/admin/questions/form.html.twig', [
            'form' => $form->createView(),
            'question' => $question,
            'mode' => 'new',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_question_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Question $question, Request $request, EntityManagerInterface $em, ExamPdfUpdater $pdfUpdater): Response
    {
        if ($question->getType() === 'MCQ') {
            $this->ensureDefaultChoices($question, 4);
        }

        $form = $this->createForm(QuestionType::class, $question, [
            'evaluation_locked' => (bool) $request->query->get('evaluationId'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $question->setContent(trim((string) $question->getContent()));
            if ($question->getContent() === '') {
                $this->addFlash('error', 'Veuillez saisir le contenu de la question/exercice.');
                return $this->render('pages/admin/questions/form.html.twig', [
                    'form' => $form->createView(),
                    'question' => $question,
                    'mode' => 'edit',
                ]);
            }

            if ($question->getType() === 'TEXT') {
                foreach ($question->getAnswers() as $a) {
                    if ($a->isChoice()) {
                        $em->remove($a);
                    }
                }
            }

            if ($question->getType() === 'MCQ') {
                $correctCount = 0;

                foreach ($question->getAnswers() as $a) {
                    $a->setRole('CHOICE');
                    $a->setStudent(null);
                    if ($a->getIsCorrect() === true) $correctCount++;
                }

                if ($correctCount !== 1) {
                    $this->addFlash('error', 'For MCQ, you must select exactly ONE correct answer.');
                    return $this->render('pages/admin/questions/form.html.twig', [
                        'form' => $form->createView(),
                        'question' => $question,
                        'mode' => 'edit',
                    ]);
                }
            }

            if ($question->getEvaluation()) {
                $question->getEvaluation()->calculateTotalScore();
            }

            $em->flush();

            // ✅ Regénérer PDF si EXAM
            $eval = $question->getEvaluation();
            if ($eval && strtoupper((string) $eval->getType()) === 'EXAM') {
                $pdfUpdater->regeneratePdf($eval);
            }

            $this->addFlash('success', 'Question updated successfully ✅');

            $back = $request->query->get('back');
            if ($back) return $this->redirect($back);

            return $this->redirectToRoute('admin_question_index');
        }

        return $this->render('pages/admin/questions/form.html.twig', [
            'form' => $form->createView(),
            'question' => $question,
            'mode' => 'edit',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_question_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Question $question, Request $request, EntityManagerInterface $em, ExamPdfUpdater $pdfUpdater): Response
    {
        if (!$this->isCsrfTokenValid('delete_question_'.$question->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $evaluation = $question->getEvaluation();

        $em->remove($question);
        $em->flush();

        if ($evaluation) {
            $evaluation->calculateTotalScore();
            $em->flush();

            // ✅ Regénérer PDF si EXAM
            if (strtoupper((string) $evaluation->getType()) === 'EXAM') {
                $pdfUpdater->regeneratePdf($evaluation);
            }
        }

        $this->addFlash('success', 'Question deleted successfully 🗑️');

        $back = $request->query->get('back');
        if ($back) return $this->redirect($back);

        return $this->redirectToRoute('admin_question_index');
    }

    #[Route('/{id}/show', name: 'admin_question_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Question $question, Request $request): Response
    {
        $back = $request->query->get('back');

        return $this->render('pages/admin/questions/show.html.twig', [
            'question' => $question,
            'back' => $back,
        ]);
    }

    private function ensureDefaultChoices(Question $question, int $count = 4): void
    {
        $choices = [];
        foreach ($question->getAnswers() as $a) {
            if ($a->isChoice()) $choices[] = $a;
        }

        $missing = $count - count($choices);
        for ($i = 0; $i < $missing; $i++) {
            $a = new Answer();
            $a->setRole('CHOICE');
            $a->setIsCorrect(false);
            $a->setContent('');
            $question->addAnswer($a);
        }
    }
}
