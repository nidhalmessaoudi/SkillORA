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
class AdminSlotController extends AbstractController
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
            if (empty($formData['start_date'])) $errors['start_date'] = 'Date de début requise.';
            if (empty($formData['start_time'])) $errors['start_time'] = 'Heure de début requise.';
            if (empty($formData['end_date']))   $errors['end_date'] = 'Date de fin requise.';
            if (empty($formData['end_time']))   $errors['end_time'] = 'Heure de fin requise.';

            if (empty($errors)) {
                try {
                    $startAt = new \DateTime($formData['start_date'] . ' ' . $formData['start_time']);
                    $endAt   = new \DateTime($formData['end_date'] . ' ' . $formData['end_time']);

                    if ($endAt <= $startAt) {
                        $errors['end_date'] = 'La fin doit être après le début.';
                    } else {
                        $slot = new AvailabilitySlot();
                        $slot->setStartAt($startAt);
                        $slot->setEndAt($endAt);
                        $slot->setIsBooked(false);

                        $this->entityManager->persist($slot);
                        $this->entityManager->flush();

                        $this->addFlash('success', 'Créneau créé avec succès.');
                        return $this->redirectToRoute('admin_slots_index');
                    }
                } catch (\Exception $e) {
                    $errors['general'] = 'Erreur lors de la création du créneau.';
                }
            }

            if (!empty($errors)) {
                $this->addFlash('error', 'Veuillez corriger les erreurs.');
            }
        }

        return $this->render('availability_slots/new.html.twig', [
            'form_data' => $formData,
            'errors'    => $errors,
        ]);
    }

    #[Route('/{id}', name: 'admin_slots_show', requirements: ['id' => '\d+'])]
    public function show(AvailabilitySlot $slot): Response
    {
        return $this->render('availability_slots/show.html.twig', [
            'slot' => $slot,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_slots_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, AvailabilitySlot $slot): Response
    {
        $formData = [
            'start_date' => $request->request->get('start_date', $slot->getStartAt()->format('Y-m-d')),
            'start_time' => $request->request->get('start_time', $slot->getStartAt()->format('H:i')),
            'end_date'   => $request->request->get('end_date', $slot->getEndAt()->format('Y-m-d')),
            'end_time'   => $request->request->get('end_time', $slot->getEndAt()->format('H:i')),
            'is_booked'  => $request->request->get('is_booked', $slot->isBooked() ? '1' : '0'),
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            if (empty($formData['start_date'])) $errors['start_date'] = 'Date de début requise.';
            if (empty($formData['start_time'])) $errors['start_time'] = 'Heure de début requise.';
            if (empty($formData['end_date']))   $errors['end_date'] = 'Date de fin requise.';
            if (empty($formData['end_time']))   $errors['end_time'] = 'Heure de fin requise.';

            if (empty($errors)) {
                try {
                    $startAt = new \DateTime($formData['start_date'] . ' ' . $formData['start_time']);
                    $endAt   = new \DateTime($formData['end_date'] . ' ' . $formData['end_time']);

                    if ($endAt <= $startAt) {
                        $errors['end_date'] = 'La fin doit être après le début.';
                    } else {
                        $slot->setStartAt($startAt);
                        $slot->setEndAt($endAt);
                        $slot->setIsBooked($request->request->get('is_booked') === '1');

                        $this->entityManager->flush();

                        $this->addFlash('success', 'Créneau modifié avec succès.');
                        return $this->redirectToRoute('admin_slots_index');
                    }
                } catch (\Exception $e) {
                    $errors['general'] = 'Erreur lors de la modification du créneau.';
                }
            }

            if (!empty($errors)) {
                $this->addFlash('error', 'Veuillez corriger les erreurs.');
            }
        }

        return $this->render('availability_slots/edit.html.twig', [
            'slot' => $slot,
            'form_data' => $formData,
            'errors'    => $errors,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_slots_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, AvailabilitySlot $slot): Response
    {
        if ($this->isCsrfTokenValid('delete' . $slot->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($slot);
            $this->entityManager->flush();

            $this->addFlash('success', 'Créneau supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_slots_index');
    }
}
