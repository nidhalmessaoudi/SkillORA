<?php

namespace App\Service;

use App\Entity\Certificate;
use App\Entity\Enrollment;
use App\Repository\CertificateRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CertificateService
{
    public function __construct(
        private readonly CertificateRepository $certificateRepository,
        private readonly CourseProgressService $courseProgressService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function issueIfEligible(Enrollment $enrollment): ?Certificate
    {
        $existing = $this->certificateRepository->findOneBy(['enrollment' => $enrollment]);
        if ($existing) {
            return $existing;
        }

        $totalLessons = $this->courseProgressService->getTotalLessons($enrollment->getCourse());
        if ($totalLessons <= 0) {
            return null;
        }

        $progress = $this->courseProgressService->calculateProgress($enrollment);
        if ($progress < 100) {
            return null;
        }

        $certificate = new Certificate();
        $certificate->setEnrollment($enrollment);
        $certificate->setCertificateCode($this->generateUniqueCode());
        $certificate->setIssuedAt(new \DateTimeImmutable());
        $certificate->setStudentName($enrollment->getUser()->getFullName());
        $certificate->setCourseTitle((string) $enrollment->getCourse()->getTitle());

        $this->entityManager->persist($certificate);

        return $certificate;
    }

    public function generatePdfBinary(Certificate $certificate): string
    {
        $verifyUrl = $this->urlGenerator->generate(
            'certificate_verify',
            ['certificateCode' => $certificate->getCertificateCode()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $qrCode = QrCode::create($verifyUrl)
            ->setSize(180)
            ->setMargin(8);

        $qrPng = (new PngWriter())->write($qrCode)->getDataUri();
        $logoDataUri = $this->getLogoDataUri();

        $html = $this->buildCertificateHtml($certificate, $verifyUrl, $qrPng, $logoDataUri);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function generateUniqueCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = 'SKH-' . strtoupper(bin2hex(random_bytes(6)));
            if (!$this->certificateRepository->findOneByCode($code)) {
                return $code;
            }
        }

        return 'SKH-' . strtoupper(bin2hex(random_bytes(8)));
    }

    private function buildCertificateHtml(
        Certificate $certificate,
        string $verifyUrl,
        string $qrDataUri,
        ?string $logoDataUri,
    ): string
    {
        $studentName = htmlspecialchars($certificate->getStudentName(), ENT_QUOTES, 'UTF-8');
        $courseTitle = htmlspecialchars($certificate->getCourseTitle(), ENT_QUOTES, 'UTF-8');
        $issuedAt = $certificate->getIssuedAt()->format('F d, Y');
        $code = htmlspecialchars($certificate->getCertificateCode(), ENT_QUOTES, 'UTF-8');
        $verifyUrlEscaped = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
        $logoHtml = $logoDataUri
            ? '<img src="' . $logoDataUri . '" alt="SkillORA Logo" class="logo" />'
            : '<div class="brand-text">SkillORA</div>';

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    @page { size: A4 landscape; margin: 0; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0f172a; background: #f1f5f9; }
    .page {
      padding: 14px;
    }
    .frame {
      border: 6px solid #1d4ed8;
      background: #ffffff;
      padding: 10px;
    }
    .inner-frame { border: 2px solid #93c5fd; padding: 18px 28px 18px; }
    .header {
      margin-bottom: 12px;
      text-align: center;
    }
    .logo { height: 66px; max-width: 300px; margin: 0 auto 10px; display: block; }
    .brand-text {
      font-size: 34px;
      font-weight: 700;
      color: #1d4ed8;
      margin-bottom: 8px;
    }
    .subtitle {
      font-size: 14px;
      letter-spacing: 2px;
      color: #1e40af;
      text-transform: uppercase;
      font-weight: 700;
    }
    .title {
      text-align: center;
      font-size: 44px;
      margin: 8px 0 10px;
      color: #1e3a8a;
      font-weight: 700;
    }
    .text {
      text-align: center;
      font-size: 22px;
      color: #334155;
      margin: 0;
      line-height: 1.3;
    }
    .student {
      text-align: center;
      font-size: 38px;
      color: #1d4ed8;
      margin: 10px 0 5px;
      font-weight: 700;
    }
    .course {
      text-align: center;
      font-size: 30px;
      color: #0f172a;
      margin: 6px 0 10px;
      font-weight: 700;
    }
    .meta {
      text-align: center;
      font-size: 16px;
      color: #475569;
      margin-bottom: 10px;
    }
    .footer {
      width: 100%;
      border-top: 1px solid #bfdbfe;
      padding-top: 14px;
      margin-top: 20px;
    }
    .footer-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    .footer-left { width: 72%; vertical-align: top; }
    .footer-right { width: 28%; text-align: right; vertical-align: top; }
    .label {
      font-size: 14px;
      color: #64748b;
      margin-bottom: 4px;
    }
    .code {
      font-family: DejaVu Sans Mono, monospace;
      font-size: 20px;
      font-weight: 700;
      color: #1e3a8a;
      margin-bottom: 8px;
    }
    .verify {
      font-size: 12px;
      color: #475569;
      max-width: 520px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .qr {
      width: 122px;
      height: 122px;
      border: 1px solid #cbd5e1;
      padding: 6px;
      background: #fff;
      display: inline-block;
      margin-bottom: 4px;
    }
    .small-note {
      font-size: 12px;
      color: #64748b;
      width: 134px;
      margin-left: auto;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="frame">
      <div class="inner-frame">
        <div class="header">
          {$logoHtml}
          <div class="subtitle">Certificate of Completion</div>
        </div>

        <div class="title">SkillORA Certificate</div>
        <p class="text">This certifies that</p>
        <div class="student">{$studentName}</div>
        <p class="text">has successfully completed</p>
        <div class="course">{$courseTitle}</div>
        <div class="meta">Issued on {$issuedAt}</div>

        <div class="footer">
          <table class="footer-table">
            <tr>
              <td class="footer-left">
                <div class="label">Certificate Code</div>
                <div class="code">{$code}</div>
                <div class="verify">Verification URL: {$verifyUrlEscaped}</div>
              </td>
              <td class="footer-right">
                <img src="{$qrDataUri}" alt="Verification QR code" class="qr" />
                <div class="small-note">Scan to verify</div>
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function getLogoDataUri(): ?string
    {
        $logoPath = $this->projectDir . '/assets/SkillORA_Logo.png';
        if (!is_file($logoPath) || !is_readable($logoPath)) {
            return null;
        }

        $content = @file_get_contents($logoPath);
        if ($content === false || $content === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($content);
    }
}
