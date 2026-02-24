<?php

namespace App\Controller;

use App\Entity\Certificate;
use App\Entity\User;
use App\Repository\CertificateRepository;
use App\Service\CertificateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CertificateController extends AbstractController
{
    #[Route('/certificates/{certificateCode}/download', name: 'certificate_download', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function download(
        string $certificateCode,
        CertificateRepository $certificateRepository,
        CertificateService $certificateService,
    ): Response {
        $certificate = $certificateRepository->findOneByCode($certificateCode);
        if (!$certificate instanceof Certificate) {
            throw $this->createNotFoundException('Certificate not found.');
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $owner = $certificate->getEnrollment()?->getUser();
        if (!$owner || $owner->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You cannot download this certificate.');
        }

        $pdf = $certificateService->generatePdfBinary($certificate);
        $filename = 'certificate-' . strtolower($certificate->getCertificateCode()) . '.pdf';

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('/certificates/verify/{certificateCode}', name: 'certificate_verify', methods: ['GET'])]
    public function verify(string $certificateCode, CertificateRepository $certificateRepository): Response
    {
        $certificate = $certificateRepository->findOneByCode($certificateCode);

        return $this->render('pages/certificates/verify.html.twig', [
            'certificate' => $certificate,
            'is_valid' => $certificate instanceof Certificate,
            'code' => $certificateCode,
        ]);
    }
}
