<?php

namespace App\Controller;

use App\Entity\Evaluation;
use App\Entity\Question;
use App\Form\EvaluationType;
use App\Repository\EvaluationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        $type = $request->query->get('type'); // QUIZ | EXAM | null
        $evaluationId = $request->query->get('evaluationId'); // id | null

        // ✅ list evaluations (optionally filtered)
        $evaluations = $type
            ? $evaluationRepository->findBy(['type' => $type], ['createdAt' => 'DESC'])
            : $evaluationRepository->findBy([], ['createdAt' => 'DESC']);

        $selectedEvaluation = null;
        $questions = [];

        if ($evaluationId) {
            $selectedEvaluation = $evaluationRepository->find($evaluationId);

            if ($selectedEvaluation) {
                // ✅ EXAM => redirect مباشرة لصفحة Manage Exam
                if (strtoupper((string) $selectedEvaluation->getType()) === 'EXAM') {
                    return $this->redirectToRoute('admin_exam_manage', [
                        'id' => $selectedEvaluation->getId()
                    ]);
                }

                // ✅ QUIZ => نجيب questions ونعرضهم في نفس الصفحة
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
        if ($this->isCsrfTokenValid('delete'.$evaluation->getId(), $request->request->get('_token'))) {
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

    // ✅ sauvegarde DB
    $evaluation->setPdfPath($newFilename);
    $em->flush();

    $this->addFlash('success', 'PDF importé ✅');
    return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
}

}
