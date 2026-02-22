<?php

namespace App\Controller;

use App\Entity\AvailabilitySlot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SlotsFrontCrudController extends AbstractController
{
    #[Route('/slots/front/new', name: 'slots_front_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $formData = [
            'start_date' => $request->request->get('start_date', ''),
            'start_time' => $request->request->get('start_time', ''),
            'end_date'   => $request->request->get('end_date', ''),
            'end_time'   => $request->request->get('end_time', ''),
        ];
        $errors = [];
        if ($request->isMethod('POST')) {
            if (empty($formData['start_date'])) $errors['start_date'] = 'Start date required.';
            if (empty($formData['start_time'])) $errors['start_time'] = 'Start time required.';
            if (empty($formData['end_date']))   $errors['end_date'] = 'End date required.';
            if (empty($formData['end_time']))   $errors['end_time'] = 'End time required.';
            if (empty($errors)) {
                $startAt = new \DateTime($formData['start_date'] . ' ' . $formData['start_time']);
                $endAt   = new \DateTime($formData['end_date'] . ' ' . $formData['end_time']);
                if ($endAt <= $startAt) {
                    $errors['end_date'] = 'End must be after start.';
                }
            }
            if (empty($errors)) {
                $slot = new AvailabilitySlot();
                $slot->setStartAt($startAt);
                $slot->setEndAt($endAt);
                $slot->setIsBooked(false);
                $entityManager->persist($slot);
                $entityManager->flush();
                $this->addFlash('success', 'Slot created.');
                return $this->redirectToRoute('slots_front');
            }
            $this->addFlash('error', 'Please correct errors.');
        }
        return $this->render('slots/new.html.twig', [
            'form_data' => $formData,
            'errors'    => $errors,
        ]);
    }

    #[Route('/slots/front/{id}/edit', name: 'slots_front_edit')]
    public function edit(Request $request, AvailabilitySlot $slot, EntityManagerInterface $entityManager): Response
    {
        $formData = [
            'start_date' => $request->isMethod('POST') ? $request->request->get('start_date', '') : $slot->getStartAt()->format('Y-m-d'),
            'start_time' => $request->isMethod('POST') ? $request->request->get('start_time', '') : $slot->getStartAt()->format('H:i'),
            'end_date'   => $request->isMethod('POST') ? $request->request->get('end_date', '')   : $slot->getEndAt()->format('Y-m-d'),
            'end_time'   => $request->isMethod('POST') ? $request->request->get('end_time', '')   : $slot->getEndAt()->format('H:i'),
        ];
        $errors = [];
        if ($request->isMethod('POST')) {
            if (empty($formData['start_date'])) $errors['start_date'] = 'Start date required.';
            if (empty($formData['start_time'])) $errors['start_time'] = 'Start time required.';
            if (empty($formData['end_date']))   $errors['end_date'] = 'End date required.';
            if (empty($formData['end_time']))   $errors['end_time'] = 'End time required.';
            if (empty($errors)) {
                $startAt = new \DateTime($formData['start_date'] . ' ' . $formData['start_time']);
                $endAt   = new \DateTime($formData['end_date'] . ' ' . $formData['end_time']);
                if ($endAt <= $startAt) {
                    $errors['end_date'] = 'End must be after start.';
                }
            }
            if (empty($errors)) {
                $slot->setStartAt($startAt);
                $slot->setEndAt($endAt);
                $entityManager->flush();
                $this->addFlash('success', 'Slot updated.');
                return $this->redirectToRoute('slots_front');
            }
            $this->addFlash('error', 'Please correct errors.');
        }
        return $this->render('slots/edit.html.twig', [
            'slot'      => $slot,
            'form_data' => $formData,
            'errors'    => $errors,
        ]);
    }

    #[Route('/slots/front/{id}/delete', name: 'slots_front_delete', methods: ['POST'])]
    public function delete(Request $request, AvailabilitySlot $slot, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $slot->getId(), $request->request->get('_token'))) {
            $entityManager->remove($slot);
            $entityManager->flush();
            $this->addFlash('success', 'Slot deleted.');
        }
        return $this->redirectToRoute('slots_front');
    }
}
