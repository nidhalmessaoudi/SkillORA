<?php

namespace App\Controller;

use App\Entity\AvailabilitySlot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SlotsFrontCrudController extends AbstractController
{
    private const PROFESSOR_ONLY_FLASH = 'Accès refusé : seuls les professeurs peuvent gérer les créneaux.';

    #[Route('/slots/front/new', name: 'slots_front_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $wantsJson = $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept', ''), 'application/json');

        $user = $this->getUser();
        if (!$user) {
            if ($wantsJson) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Authentification requise.',
                ], Response::HTTP_UNAUTHORIZED);
            }
            return $this->redirect('/login');
        }

        if (!$this->isGranted('ROLE_PROFESSOR')) {
            if ($wantsJson) {
                return new JsonResponse([
                    'success' => false,
                    'message' => self::PROFESSOR_ONLY_FLASH,
                ], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('error', self::PROFESSOR_ONLY_FLASH);
            return $this->redirectToRoute('slots_front');
        }

        $formData = [
            'start_date' => $request->request->get('start_date', ''),
            'start_time' => $request->request->get('start_time', ''),
            'end_date'   => $request->request->get('end_date', ''),
            'end_time'   => $request->request->get('end_time', ''),
            'location_label' => $request->request->get('location_label', ''),
            'location_lat' => $request->request->get('location_lat', ''),
            'location_lng' => $request->request->get('location_lng', ''),
        ];
        $errors = [];
        if ($request->isMethod('POST')) {
            if (empty($formData['start_date'])) $errors['start_date'] = 'Date de début requise.';
            if (empty($formData['start_time'])) $errors['start_time'] = 'Heure de début requise.';
            if (empty($formData['end_date']))   $errors['end_date'] = 'Date de fin requise.';
            if (empty($formData['end_time']))   $errors['end_time'] = 'Heure de fin requise.';
            if (empty($errors)) {
                $startAt = new \DateTime($formData['start_date'] . ' ' . $formData['start_time']);
                $endAt   = new \DateTime($formData['end_date'] . ' ' . $formData['end_time']);
                if ($endAt <= $startAt) {
                    $errors['end_date'] = 'La fin doit être après le début.';
                }
                if ($startAt <= new \DateTime()) {
                    $errors['start_date'] = 'Le créneau doit être dans le futur.';
                }
            }
            if (empty($errors)) {
                $slot = new AvailabilitySlot();
                $slot->setStartAt($startAt);
                $slot->setEndAt($endAt);
                $slot->setIsBooked(false);
                $slot->setProfessor($user);
                $locationLabel = trim((string) $formData['location_label']);
                $slot->setLocationLabel($locationLabel !== '' ? $locationLabel : null);
                $slot->setLocationLat(is_numeric($formData['location_lat']) ? (float) $formData['location_lat'] : null);
                $slot->setLocationLng(is_numeric($formData['location_lng']) ? (float) $formData['location_lng'] : null);
                $entityManager->persist($slot);
                $entityManager->flush();

                if ($wantsJson) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Créneau créé avec succès.',
                    ]);
                }

                $this->addFlash('success', 'Créneau créé avec succès.');
                return $this->redirectToRoute('slots_front');
            }

            if ($wantsJson) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Veuillez corriger les erreurs.',
                    'errors' => $errors,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('error', 'Veuillez corriger les erreurs.');
        }
        return $this->render('slots/new.html.twig', [
            'form_data' => $formData,
            'errors'    => $errors,
        ]);
    }

    #[Route('/slots/front/{id}/edit', name: 'slots_front_edit')]
    public function edit(Request $request, AvailabilitySlot $slot, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirect('/login');
        }

        if (!$this->isGranted('ROLE_PROFESSOR')) {
            $this->addFlash('error', self::PROFESSOR_ONLY_FLASH);
            return $this->redirectToRoute('slots_front');
        }

        // Only the owning professor can edit
        if ($slot->getProfessor() && $slot->getProfessor()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $formData = [
            'start_date' => $request->isMethod('POST') ? $request->request->get('start_date', '') : $slot->getStartAt()->format('Y-m-d'),
            'start_time' => $request->isMethod('POST') ? $request->request->get('start_time', '') : $slot->getStartAt()->format('H:i'),
            'end_date'   => $request->isMethod('POST') ? $request->request->get('end_date', '')   : $slot->getEndAt()->format('Y-m-d'),
            'end_time'   => $request->isMethod('POST') ? $request->request->get('end_time', '')   : $slot->getEndAt()->format('H:i'),
            'location_label' => $request->isMethod('POST') ? $request->request->get('location_label', '') : ($slot->getLocationLabel() ?? ''),
            'location_lat' => $request->isMethod('POST') ? $request->request->get('location_lat', '') : ($slot->getLocationLat() !== null ? (string) $slot->getLocationLat() : ''),
            'location_lng' => $request->isMethod('POST') ? $request->request->get('location_lng', '') : ($slot->getLocationLng() !== null ? (string) $slot->getLocationLng() : ''),
        ];
        $errors = [];
        if ($request->isMethod('POST')) {
            if (empty($formData['start_date'])) $errors['start_date'] = 'Date de début requise.';
            if (empty($formData['start_time'])) $errors['start_time'] = 'Heure de début requise.';
            if (empty($formData['end_date']))   $errors['end_date'] = 'Date de fin requise.';
            if (empty($formData['end_time']))   $errors['end_time'] = 'Heure de fin requise.';
            if (empty($errors)) {
                $startAt = new \DateTime($formData['start_date'] . ' ' . $formData['start_time']);
                $endAt   = new \DateTime($formData['end_date'] . ' ' . $formData['end_time']);
                if ($endAt <= $startAt) {
                    $errors['end_date'] = 'La fin doit être après le début.';
                }
            }
            if (empty($errors)) {
                $slot->setStartAt($startAt);
                $slot->setEndAt($endAt);
                $locationLabel = trim((string) $formData['location_label']);
                $slot->setLocationLabel($locationLabel !== '' ? $locationLabel : null);
                $slot->setLocationLat(is_numeric($formData['location_lat']) ? (float) $formData['location_lat'] : null);
                $slot->setLocationLng(is_numeric($formData['location_lng']) ? (float) $formData['location_lng'] : null);
                $entityManager->flush();
                $this->addFlash('success', 'Créneau modifié avec succès.');
                return $this->redirectToRoute('slots_front');
            }
            $this->addFlash('error', 'Veuillez corriger les erreurs.');
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
        $user = $this->getUser();
        if (!$user) {
            return $this->redirect('/login');
        }

        if (!$this->isGranted('ROLE_PROFESSOR')) {
            $this->addFlash('error', self::PROFESSOR_ONLY_FLASH);
            return $this->redirectToRoute('slots_front');
        }

        // Only the owning professor can delete
        if ($slot->getProfessor() && $slot->getProfessor()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete' . $slot->getId(), $request->request->get('_token'))) {
            $entityManager->remove($slot);
            $entityManager->flush();
            $this->addFlash('success', 'Créneau supprimé avec succès.');
        }
        return $this->redirectToRoute('slots_front');
    }
}
