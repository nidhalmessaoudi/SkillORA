<?php

namespace App\Service;

use App\Entity\Evaluation;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

class ExamPdfUpdater
{
    public function __construct(
        private EntityManagerInterface $em,
        private ParameterBagInterface $params
    ) {}

    public function regeneratePdf(Evaluation $evaluation): void
    {
        if (strtoupper((string) $evaluation->getType()) !== 'EXAM') {
            return;
        }

        // ✅ Récupérer exercices triés
        $questions = $this->em->getRepository(Question::class)->findBy(
            ['evaluation' => $evaluation],
            ['id' => 'ASC']
        );

        // ✅ Générer DOCX depuis DB
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addText($evaluation->getTitle(), ['bold' => true, 'size' => 16]);
        $section->addTextBreak(1);

        $i = 1;
        foreach ($questions as $q) {
            $section->addText("Exercice {$i} ({$q->getScore()} pts)", ['bold' => true]);
            $section->addText($q->getContent() ?: '');
            $section->addTextBreak(1);
            $i++;
        }

        $docxDir = rtrim((string) $this->params->get('exam_docx_upload_dir'), '/\\');
        $pdfDir  = rtrim((string) $this->params->get('exam_pdf_upload_dir'), '/\\');

        // ✅ NOM FIXE => un seul fichier par EXAM
        $baseName = 'exam_' . $evaluation->getId();
        $docxFilename = $baseName . '.docx';
        $pdfFilename  = $baseName . '.pdf';

        $docxFullPath = $docxDir . DIRECTORY_SEPARATOR . $docxFilename;
        $pdfFullPath  = $pdfDir  . DIRECTORY_SEPARATOR . $pdfFilename;

        // ✅ Supprimer anciens fichiers pour éviter conflit Windows
        if (is_file($docxFullPath)) @unlink($docxFullPath);
        if (is_file($pdfFullPath))  @unlink($pdfFullPath);

        // ✅ Sauvegarder DOCX
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($docxFullPath);

        // ✅ Convertir DOCX -> PDF via LibreOffice
        $soffice = (string) $this->params->get('libreoffice_soffice_path');
        $soffice = str_replace('\\', '/', $soffice);

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

        if (!is_file($pdfFullPath)) {
            throw new \RuntimeException(
                "PDF non généré: {$pdfFullPath} | ERR: " . $process->getErrorOutput() . " | OUT: " . $process->getOutput()
            );
        }

        // ✅ Mettre à jour chemins (fixes)
        $evaluation->setDocxPath('/uploads/docx/' . $docxFilename);
        $evaluation->setPdfPath('/uploads/pdf/' . $pdfFilename);

        $this->em->flush();
    }
}
