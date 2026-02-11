<?php

namespace App\Controller;

use App\Entity\AvailabilitySlot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/slots')]
#[IsGranted('ROLE_ADMIN')]
class AvailabilitySlotsController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('/', name: 'admin_slots_index')]
    public function index(): Response
    {
        $slots = $this->entityManager->getRepository(AvailabilitySlot::class)->findBy([], ['startAt' => 'DESC']);

        return $this->render('availability_slots/index.html.twig', [
            'slots' => $slots,
        ]);
    }

    #[Route('/new', name: 'admin_slots_new')]
    public function new(Request $request): Response
    {
        $formData = [
            'start_date' => $request->request->get('start_date', ''),
            'start_time' => $request->request->get('start_time', ''),
            'end_date'   => $request->request->get('end_date', ''),
            'end_time'   => $request->request->get('end_time', ''),
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validateSlotForm($formData);

            if (empty($errors)) {
                $startAt = new \DateTime($formData['start_date'] . ' ' . $formData['start_time']);
                $endAt   = new \DateTime($formData['end_date'] . ' ' . $formData['end_time']);

                if ($endAt <= $startAt) {
                    $errors['end_date'] = 'La date/heure de fin doit être postérieure à la date/heure de début.';
                }
            }

            if (empty($errors)) {
                $slot = new AvailabilitySlot();
                $slot->setStartAt($startAt);
                $slot->setEndAt($endAt);
                $slot->setIsBooked(false);

                $this->entityManager->persist($slot);
                $this->entityManager->flush();

                $this->addFlash('success', 'Créneau créé avec succès.');
                return $this->redirectToRoute('admin_slots_index');
            }

            $this->addFlash('error', 'Veuillez corriger les erreurs.');
        }

        return $this->render('availability_slots/new.html.twig', [
            'form_data' => $formData,
            'errors'    => $errors,
        ]);
    }

    #[Route('/{id}', name: 'admin_slots_show')]
    public function show(AvailabilitySlot $slot): Response
    {
        return $this->render('availability_slots/show.html.twig', [
            'slot' => $slot,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_slots_edit')]
    public function edit(Request $request, AvailabilitySlot $slot): Response
    {
        $formData = [
            'start_date' => $request->isMethod('POST') ? $request->request->get('start_date', '') : $slot->getStartAt()->format('Y-m-d'),
            'start_time' => $request->isMethod('POST') ? $request->request->get('start_time', '') : $slot->getStartAt()->format('H:i'),
            'end_date'   => $request->isMethod('POST') ? $request->request->get('end_date', '')   : $slot->getEndAt()->format('Y-m-d'),
            'end_time'   => $request->isMethod('POST') ? $request->request->get('end_time', '')   : $slot->getEndAt()->format('H:i'),
            'is_booked'  => $request->isMethod('POST') ? $request->request->has('is_booked')       : $slot->isBooked(),
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validateSlotForm($formData);

            if (empty($errors)) {
                $startAt = new \DateTime($formData['start_date'] . ' ' . $formData['start_time']);
                $endAt   = new \DateTime($formData['end_date'] . ' ' . $formData['end_time']);

                if ($endAt <= $startAt) {
                    $errors['end_date'] = 'La date/heure de fin doit être postérieure à la date/heure de début.';
                }
            }

            if (empty($errors)) {
                $slot->setStartAt($startAt);
                $slot->setEndAt($endAt);
                $slot->setIsBooked((bool) $formData['is_booked']);

                $this->entityManager->flush();

                $this->addFlash('success', 'Créneau mis à jour avec succès.');
                return $this->redirectToRoute('admin_slots_show', ['id' => $slot->getId()]);
            }

            $this->addFlash('error', 'Veuillez corriger les erreurs.');
        }

        return $this->render('availability_slots/edit.html.twig', [
            'slot'      => $slot,
            'form_data' => $formData,
            'errors'    => $errors,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_slots_delete', methods: ['POST'])]
    public function delete(Request $request, AvailabilitySlot $slot): Response
    {
        if ($this->isCsrfTokenValid('delete' . $slot->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($slot);
            $this->entityManager->flush();
            $this->addFlash('success', 'Créneau supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_slots_index');
    }

    private function validateSlotForm(array $data): array
    {
        $errors = [];
        if (empty($data['start_date'])) {
            $errors['start_date'] = 'La date de début est obligatoire.';
        }
        if (empty($data['start_time'])) {
            $errors['start_time'] = 'L\'heure de début est obligatoire.';
        }
        if (empty($data['end_date'])) {
            $errors['end_date'] = 'La date de fin est obligatoire.';
        }
        if (empty($data['end_time'])) {
            $errors['end_time'] = 'L\'heure de fin est obligatoire.';
        }
        return $errors;
    }
}