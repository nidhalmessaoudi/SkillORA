<?php

namespace App\Controller;

use App\Entity\Evaluation;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Process\Process;


#[Route('/admin/exam')]
#[IsGranted('ROLE_ADMIN')]
class AdminExamController extends AbstractController
{
    #[Route('/{id}/manage', name: 'admin_exam_manage', methods: ['GET'])]
    public function manage(Evaluation $evaluation, EntityManagerInterface $em): Response
    {
        if (strtoupper((string) $evaluation->getType()) !== 'EXAM') {
            throw $this->createNotFoundException('Not an exam evaluation');
        }

        // ✅ Fix auto si ancien pdfPath stocké sans /uploads/pdf/
        $pdfPath = $evaluation->getPdfPath();
        if ($pdfPath && !str_starts_with($pdfPath, '/uploads/pdf/')) {
            if (!str_starts_with($pdfPath, '/')) {
                $evaluation->setPdfPath('/uploads/pdf/' . $pdfPath);
                $em->flush();
            }
        }

        $questions = $em->getRepository(Question::class)->findBy(
            ['evaluation' => $evaluation],
            ['id' => 'ASC']
        );

        return $this->render('pages/admin/exams/manage.html.twig', [
            'evaluation' => $evaluation,
            'questions' => $questions,
        ]);
    }

    #[Route('/{id}/import-docx', name: 'admin_exam_import_docx', methods: ['POST'])]
    public function importDocx(Evaluation $evaluation, Request $request, EntityManagerInterface $em): Response
    {
        if (strtoupper((string) $evaluation->getType()) !== 'EXAM') {
            throw $this->createNotFoundException('Not an exam evaluation');
        }

        if (!$this->isCsrfTokenValid('import_exam_docx_' . $evaluation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $file = $request->files->get('docx');
        if (!$file) {
            $this->addFlash('error', 'Veuillez choisir un fichier Word (.docx).');
            return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== 'docx') {
            $this->addFlash('error', 'Format invalide. Veuillez uploader un .docx');
            return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        $docxDir = $this->getParameter('exam_docx_upload_dir');
        $pdfDir  = $this->getParameter('exam_pdf_upload_dir');

        $baseName = 'exam_' . $evaluation->getId() . '_' . uniqid();
        $docxFilename = $baseName . '.docx';
        $pdfFilename  = $baseName . '.pdf';

        // ✅ 1) Save DOCX
        $file->move($docxDir, $docxFilename);
        $evaluation->setDocxPath('/uploads/docx/' . $docxFilename);

        $docxFullPath = rtrim($docxDir, '/\\') . DIRECTORY_SEPARATOR . $docxFilename;

        // ✅ 2) Parse DOCX => create Exercises as Questions (TEXT + score)
        $phpWord = IOFactory::load($docxFullPath);

        $allText = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                if (method_exists($el, 'getText')) {
                    $allText .= $el->getText() . "\n";
                } elseif (method_exists($el, 'getElements')) {
                    foreach ($el->getElements() as $child) {
                        if (method_exists($child, 'getText')) {
                            $allText .= $child->getText() . "\n";
                        }
                    }
                }
            }
        }

        $allText = preg_replace("/\r\n|\r/", "\n", $allText);
        $allText = preg_replace("/[ \t]+/", " ", $allText);

        preg_match_all(
            '/(Exercice\s+\d+.*?\(\s*([\d,\.]+)\s*pts?\s*\))(.*?)(?=Exercice\s+\d+|\z)/si',
            $allText,
            $matches,
            PREG_SET_ORDER
        );

        if ($matches) {
            // ✅ Remplacer les anciens exercices automatiquement (important)
            foreach ($evaluation->getQuestions() as $oldQ) {
                $em->remove($oldQ);
            }

            foreach ($matches as $m) {
                $titleLine = trim($m[1]);
                $ptsRaw = str_replace(',', '.', $m[2]);
                $pts = (int) round((float) $ptsRaw);
                $body = trim($m[3]);

                $q = new Question();
                $q->setEvaluation($evaluation);
                $q->setType('TEXT');
                $q->setScore(max(1, $pts));
                $q->setContent($titleLine . "\n\n" . $body);

                $em->persist($q);
            }

            $evaluation->calculateTotalScore();
        } else {
            $this->addFlash('warning', "Word importé, mais aucun 'Exercice ... (x pts)' détecté. Vérifie le format du Word.");
        }

        // ✅ 3) Convert DOCX => PDF (LibreOffice)
        $soffice = $this->getParameter('libreoffice_soffice_path');

        // Normaliser (robuste Windows)
        $soffice = str_replace('\\', '/', $soffice);

        if (!is_file($soffice)) {
            $this->addFlash('error', "LibreOffice introuvable: soffice.exe non trouvé. Vérifie libreoffice_soffice_path.");
            $em->flush();
            return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        $process = new Process([
            $soffice,
            '--headless',
            '--nologo',
            '--nofirststartwizard',
            '--convert-to', 'pdf',
            '--outdir', $pdfDir,
            $docxFullPath,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->addFlash('error', "Conversion échouée Word → PDF : " . $process->getErrorOutput() . " | OUT: " . $process->getOutput());
            $em->flush();
            return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        $generatedPdfFullPath = rtrim($pdfDir, '/\\') . DIRECTORY_SEPARATOR . $baseName . '.pdf';
        if (!is_file($generatedPdfFullPath)) {
            $this->addFlash('error', "PDF non trouvé après conversion. Attendu: " . $generatedPdfFullPath);
            $em->flush();
            return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        $evaluation->setPdfPath('/uploads/pdf/' . $pdfFilename);

        $em->flush();
        $this->addFlash('success', 'Word importé ✅ Exercices créés ✅ PDF généré ✅');

        return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
    }

    #[Route('/{id}/delete-docx', name: 'admin_exam_delete_docx', methods: ['POST'])]
    public function deleteDocx(Evaluation $evaluation, Request $request, EntityManagerInterface $em): Response
    {
        if (strtoupper((string) $evaluation->getType()) !== 'EXAM') {
            throw $this->createNotFoundException('Not an exam evaluation');
        }

        if (!$this->isCsrfTokenValid('delete_exam_docx_' . $evaluation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $docxPath = $evaluation->getDocxPath();
        if ($docxPath) {
            $full = $this->getParameter('kernel.project_dir') . '/public' . $docxPath;
            if (is_file($full)) @unlink($full);
        }

        $evaluation->setDocxPath(null);
        $em->flush();

        $this->addFlash('success', 'Word supprimé ✅');
        return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
    }

    #[Route('/{id}/delete-pdf', name: 'admin_exam_delete_pdf', methods: ['POST'])]
    public function deletePdf(Evaluation $evaluation, Request $request, EntityManagerInterface $em): Response
    {
        if (strtoupper((string) $evaluation->getType()) !== 'EXAM') {
            throw $this->createNotFoundException('Not an exam evaluation');
        }

        if (!$this->isCsrfTokenValid('delete_exam_pdf_' . $evaluation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $pdfPath = $evaluation->getPdfPath();
        if ($pdfPath) {
            $full = $this->getParameter('kernel.project_dir') . '/public' . $pdfPath;
            if (is_file($full)) @unlink($full);
        }

        $evaluation->setPdfPath(null);
        $em->flush();

        $this->addFlash('success', 'PDF supprimé ✅');
        return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
    }
}
