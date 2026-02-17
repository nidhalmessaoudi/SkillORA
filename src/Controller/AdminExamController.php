<?php

namespace App\Controller;

use App\Entity\Evaluation;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Smalot\PdfParser\Parser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

        $questions = $em->getRepository(Question::class)->findBy(
            ['evaluation' => $evaluation],
            ['id' => 'DESC']
        );

        return $this->render('pages/admin/exams/manage.html.twig', [
            'evaluation' => $evaluation,
            'questions' => $questions,
        ]);
    }

    #[Route('/{id}/import-pdf', name: 'admin_exam_import_pdf', methods: ['POST'])]
    public function importPdf(Evaluation $evaluation, Request $request, EntityManagerInterface $em): Response
    {
        if (strtoupper((string) $evaluation->getType()) !== 'EXAM') {
            throw $this->createNotFoundException('Not an exam evaluation');
        }

        if (!$this->isCsrfTokenValid('import_exam_pdf_'.$evaluation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $file = $request->files->get('pdf');
        if (!$file) {
            $this->addFlash('error', 'Veuillez choisir un PDF.');
            return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($file->getPathname());
        $text = $pdf->getText();

        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/[ \t]+/", " ", $text);

        preg_match_all(
            '/(Exercice\s+\d+.*?\(\s*([\d,\.]+)\s*pts?\s*\))(.*?)(?=Exercice\s+\d+|\z)/si',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        if (!$matches) {
            $this->addFlash('error', "Impossible de détecter les exercices dans ce PDF.");
            return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
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
        $em->flush();

        $this->addFlash('success', 'Examen importé avec succès ✅');
        return $this->redirectToRoute('admin_exam_manage', ['id' => $evaluation->getId()]);
    }
}
