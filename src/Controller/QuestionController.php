<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Question;
use App\Form\QuestionType;
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

    // grouped[type][evaluationId] = ['evaluation' => Evaluation, 'items' => [Question...]]
    $grouped = [
        'QUIZ' => [],
        'EXAM' => [],
    ];

    foreach ($questions as $q) {
        $eval = $q->getEvaluation();
        if (!$eval) {
            continue; // ou tu peux les mettre dans un groupe "NO_EVAL"
        }

        $type = strtoupper((string) $eval->getType());
        if (!in_array($type, ['QUIZ', 'EXAM'], true)) {
            $type = 'QUIZ';
        }

        $evalId = $eval->getId();

        if (!isset($grouped[$type][$evalId])) {
            $grouped[$type][$evalId] = [
                'evaluation' => $eval,
                'items' => [],
            ];
        }

        $grouped[$type][$evalId]['items'][] = $q;
    }

    // optionnel: trier les évaluations par id desc
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
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $question = new Question();

        // ✅ IMPORTANT : valeurs par défaut pour éviter "Typed property must not be accessed..."
        $question->setType('MCQ');
        $question->setScore(1);

        // ✅ Pré-créer 4 choices A/B/C/D
        $this->ensureDefaultChoices($question, 4);

        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ Si TEXT => supprimer les choices (pas besoin)
            if ($question->getType() === 'TEXT') {
                foreach ($question->getAnswers() as $a) {
                    if ($a->isChoice()) {
                        $em->remove($a);
                    }
                }
            }

            // ✅ Si MCQ => forcer role=CHOICE et 1 seule bonne réponse
            if ($question->getType() === 'MCQ') {
                $correctCount = 0;

                foreach ($question->getAnswers() as $a) {
                    $a->setRole('CHOICE');
                    $a->setStudent(null);

                    if ($a->getIsCorrect() === true) {
                        $correctCount++;
                    }
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
            $em->flush();

            $this->addFlash('success', 'Question created successfully.');
            return $this->redirectToRoute('admin_question_index');
        }

        return $this->render('pages/admin/questions/form.html.twig', [
            'form' => $form->createView(),
            'question' => $question,
            'mode' => 'new',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_question_edit', methods: ['GET', 'POST'])]
    public function edit(Question $question, Request $request, EntityManagerInterface $em): Response
    {
        // ✅ si la question est MCQ et qu'il manque des choices, on complète
        if ($question->getType() === 'MCQ') {
            $this->ensureDefaultChoices($question, 4);
        }

        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ TEXT => supprimer les choices
            if ($question->getType() === 'TEXT') {
                foreach ($question->getAnswers() as $a) {
                    if ($a->isChoice()) {
                        $em->remove($a);
                    }
                }
            }

            // ✅ MCQ => role=CHOICE + 1 seule bonne réponse
            if ($question->getType() === 'MCQ') {
                $correctCount = 0;

                foreach ($question->getAnswers() as $a) {
                    $a->setRole('CHOICE');
                    $a->setStudent(null);

                    if ($a->getIsCorrect() === true) {
                        $correctCount++;
                    }
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

            $em->flush();

            $this->addFlash('success', 'Question updated successfully.');
            return $this->redirectToRoute('admin_question_index');
        }

        return $this->render('pages/admin/questions/form.html.twig', [
            'form' => $form->createView(),
            'question' => $question,
            'mode' => 'edit',
        ]);
    }

    #[Route('/{id}', name: 'admin_question_show', methods: ['GET'])]
    public function show(Question $question): Response
    {
        return $this->render('pages/admin/questions/show.html.twig', [
            'question' => $question,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_question_delete', methods: ['POST'])]
    public function delete(Question $question, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_question_'.$question->getId(), $request->request->get('_token'))) {
            $em->remove($question);
            $em->flush();
            $this->addFlash('success', 'Question deleted successfully.');
        }

        return $this->redirectToRoute('admin_question_index');
    }

    /**
     * ✅ Ajoute N choices CHOICE si elles n'existent pas encore.
     */
    private function ensureDefaultChoices(Question $question, int $count = 4): void
{
    $choices = [];
    foreach ($question->getAnswers() as $a) {
        if ($a->isChoice()) {
            $choices[] = $a;
        }
    }

    $missing = $count - count($choices);
    for ($i = 0; $i < $missing; $i++) {
        $a = new Answer();
        $a->setRole('CHOICE');
        $a->setIsCorrect(false);
        $a->setContent('');

        $question->addAnswer($a); // ✅
    }
}

}
