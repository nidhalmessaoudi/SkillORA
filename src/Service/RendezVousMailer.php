<?php

namespace App\Service;

use App\Entity\RendezVous;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class RendezVousMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {}

    public function sendCreatedRendezVousEmails(RendezVous $rdv): void
    {
        $student = $rdv->getStudent();
        $professor = $rdv->getProfessor();

        if (!$student || !$professor) {
            return;
        }

        $slot = $rdv->getSlot();
        $context = [
            'rdv' => $rdv,
            'dateLabel' => $slot ? $slot->getStartAt()->format('d/m/Y') : 'N/A',
            'timeLabel' => $slot ? $slot->getStartAt()->format('H:i') . ' - ' . $slot->getEndAt()->format('H:i') : 'N/A',
            'subjectLabel' => $rdv->getMessage() ?: ($rdv->getCourse() ? $rdv->getCourse()->getTitle() : 'Non précisé'),
            'professorName' => $professor->getFullName(),
            'studentName' => $student->getFullName(),
            'statusLabel' => 'En attente',
        ];

        if ($professor->getEmail()) {
            $this->sendSafely(
                (new Email())
                    ->to($professor->getEmail())
                    ->subject('Nouvelle demande de rendez-vous')
                    ->html($this->twig->render('emails/rendezvous_professor.html.twig', $context)),
                'professor',
                $rdv
            );
        }

        if ($student->getEmail()) {
            $this->sendSafely(
                (new Email())
                    ->to($student->getEmail())
                    ->subject('Votre demande de rendez-vous a été envoyée')
                    ->html($this->twig->render('emails/rendezvous_student.html.twig', $context)),
                'student',
                $rdv
            );
        }
    }

    private function sendSafely(Email $email, string $target, RendezVous $rdv): void
    {
        try {
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send rendez-vous creation email', [
                'target' => $target,
                'rdv_id' => $rdv->getId(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
