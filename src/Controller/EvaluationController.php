<?php

namespace App\Controller;

use App\Entity\Evaluation;
use App\Entity\Question;
use App\Form\EvaluationType;
use App\Repository\EvaluationRepository;

use App\Service\OllamaQuizGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/evaluation')]
#[IsGranted('ROLE_ADMIN')]
class EvaluationController extends AbstractController
{
    #[Route('/', name: 'evaluation_index', methods: ['GET'])]
    public function index(
        Request $request,
        EvaluationRepository $evaluationRepository,
        EntityManagerInterface $em
    ): Response {
        $type = $request->query->get('type');
        $evaluationId = $request->query->get('evaluationId');

        $evaluations = $type
            ? $evaluationRepository->findBy(['type' => $type], ['createdAt' => 'DESC'])
            : $evaluationRepository->findBy([], ['createdAt' => 'DESC']);

        $selectedEvaluation = null;
        $questions = [];

        if ($evaluationId) {
            $selectedEvaluation = $evaluationRepository->find($evaluationId);

            if ($selectedEvaluation) {
                if (strtoupper((string) $selectedEvaluation->getType()) === 'EXAM') {
                    return $this->redirectToRoute('admin_exam_manage', [
                        'id' => $selectedEvaluation->getId()
                    ]);
                }

                $questions = $em->getRepository(Question::class)->findBy(
                    ['evaluation' => $selectedEvaluation],
                    ['id' => 'DESC']
                );
            }
        }

        return $this->render('evaluation/index.html.twig', [
            'evaluations' => $evaluations,
            'selectedType' => $type,
            'selectedEvaluation' => $selectedEvaluation,
            'questions' => $questions,
        ]);
    }

    #[Route('/new', name: 'evaluation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $evaluation = new Evaluation();
        $form = $this->createForm(EvaluationType::class, $evaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($evaluation);
            $em->flush();

            $this->addFlash('success', 'Evaluation created successfully ✅');
            return $this->redirectToRoute('evaluation_index');
        }

        return $this->render('evaluation/new.html.twig', [
            'evaluation' => $evaluation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'evaluation_show', methods: ['GET'])]
    public function show(Evaluation $evaluation): Response
    {
        return $this->render('evaluation/show.html.twig', [
            'evaluation' => $evaluation,
        ]);
    }

    #[Route('/{id}/edit', name: 'evaluation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evaluation $evaluation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EvaluationType::class, $evaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Evaluation updated successfully ✅');
            return $this->redirectToRoute('evaluation_index');
        }

        return $this->render('evaluation/edit.html.twig', [
            'evaluation' => $evaluation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'evaluation_delete', methods: ['POST'])]
    public function delete(Request $request, Evaluation $evaluation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $evaluation->getId(), (string) $request->request->get('_token'))) {
            $em->remove($evaluation);
            $em->flush();
            $this->addFlash('success', 'Evaluation deleted successfully 🗑️');
        } else {
            $this->addFlash('danger', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('evaluation_index');
    }

    #[Route('/exam/{id}/import-pdf', name: 'admin_exam_import_pdf', methods: ['POST'])]
    public function importExamPdf(
        Evaluation $evaluation,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        if ($evaluation->getType() !== 'EXAM') {
            throw $this->createNotFoundException('Not an EXAM evaluation');
        }

        if (!$this->isCsrfTokenValid('import_exam_pdf_' . $evaluation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        /** @var UploadedFile|null $pdf */
        $pdf = $request->files->get('pdf');

        if (!$pdf) {
            $this->addFlash('danger', 'Aucun fichier envoyé.');
            return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        if (strtolower($pdf->getClientOriginalExtension()) !== 'pdf') {
            $this->addFlash('danger', 'Le fichier doit être un PDF.');
            return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        $uploadDir = $this->getParameter('exam_pdf_upload_dir');
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $originalName = pathinfo($pdf->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName);
        $newFilename = $safeName . '-' . uniqid() . '.pdf';

        $pdf->move($uploadDir, $newFilename);

        $evaluation->setPdfPath($newFilename);
        $em->flush();

        $this->addFlash('success', 'PDF importé ✅');
        return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
    }

    #[Route('/{id}/ai-generate-quiz', name: 'admin_eval_ai_generate_quiz', methods: ['POST'])]
    public function aiGenerateQuiz(
        Evaluation $evaluation,
        Request $request,
        OllamaQuizGenerator $generator
    ): Response {
        if (strtoupper((string) $evaluation->getType()) !== 'QUIZ') {
            throw $this->createNotFoundException('Not a QUIZ evaluation');
        }

        if (!$this->isCsrfTokenValid('ai_gen_quiz_' . $evaluation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        // ✅ Anti spam / anti double-submit (30 secondes) via Session
        $session = $request->getSession();
        $lockKey = 'ai_gen_quiz_lock_' . $evaluation->getId();
        $lockedUntil = (int) $session->get($lockKey, 0);

        if (time() < $lockedUntil) {
            $this->addFlash('warning', 'Génération déjà lancée. Réessaie dans quelques secondes.');
            return $this->redirectToRoute('evaluation_index', [
                'type' => $request->request->get('selectedType') ?: null,
                'evaluationId' => $evaluation->getId(),
                'topic' => $topic,
    'count' => $count,
            ]);
        }

        $session->set($lockKey, time() + 30);

        $topic = (string) $request->request->get('topic', 'Symfony framework');
        $count = (int) $request->request->get('count', 5);
        $selectedType = (string) $request->request->get('selectedType', '');

        try {
            $created = $generator->generateMcq($evaluation, $topic, $count);
            $this->addFlash('success', count($created) . ' question(s) générée(s) ✅');
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur IA: '.get_class($e).' | '.$e->getMessage());
        }

        return $this->redirectToRoute('evaluation_index', [
            'type' => $selectedType ?: null,
            'evaluationId' => $evaluation->getId(),
        ]);
    }
}