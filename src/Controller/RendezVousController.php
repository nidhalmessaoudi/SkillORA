<?php

namespace App\Controller;

use App\Entity\AvailabilitySlot;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RendezVousController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    // =============================================
    //  PUBLIC ROUTES (accessible to logged-in users)
    // =============================================

    #[Route('/rendezvous', name: 'public_rendezvous_index')]
    #[IsGranted('ROLE_USER')]
    public function publicIndex(): Response
    {
        $rendezvous = $this->entityManager->getRepository(RendezVous::class)->findBy([], ['createdAt' => 'DESC']);
        $availableSlots = $this->entityManager->getRepository(AvailabilitySlot::class)->findBy(
            ['isBooked' => false],
            ['startAt' => 'ASC']
        );

        return $this->render('rendez_vous/public_index.html.twig', [
            'rendezvous_list' => $rendezvous,
            'available_slots' => $availableSlots,
        ]);
    }

    /** AJAX: Create a new RDV */
    #[Route('/rendezvous/api/create', name: 'public_rendezvous_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function apiCreate(Request $request): JsonResponse
    {
        $slotId = $request->request->get('slot_id');
        if (!$slotId) {
            return $this->json(['success' => false, 'error' => 'Veuillez sélectionner un créneau.'], 400);
        }

        $slot = $this->entityManager->getRepository(AvailabilitySlot::class)->find((int) $slotId);
        if (!$slot) {
            return $this->json(['success' => false, 'error' => 'Créneau introuvable.'], 404);
        }
        if ($slot->isBooked()) {
            return $this->json(['success' => false, 'error' => 'Ce créneau est déjà réservé.'], 409);
        }

        $rdv = new RendezVous();
        $rdv->setSlot($slot);
        $rdv->setStatut(RendezVous::STATUS_EN_ATTENTE);
        $rdv->setOwnerToken(bin2hex(random_bytes(16)));
        $slot->setIsBooked(true);

        $this->entityManager->persist($rdv);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'rdv' => [
                'id' => $rdv->getId(),
                'statut' => $rdv->getStatut(),
                'slot_id' => $slot->getId(),
                'slot_start' => $slot->getStartAt()->format('d/m/Y H:i'),
                'slot_end' => $slot->getEndAt()->format('d/m/Y H:i'),
                'created_at' => $rdv->getCreatedAt()->format('d/m/Y H:i'),
            ],
        ]);
    }

    /** AJAX: Update RDV slot (student — only for en_attente) */
    #[Route('/rendezvous/api/{id}/update', name: 'public_rendezvous_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function apiUpdate(Request $request, RendezVous $rdv): JsonResponse
    {
        // Only allow slot modification when status is en_attente
        if ($rdv->getStatut() !== RendezVous::STATUS_EN_ATTENTE) {
            return $this->json(['success' => false, 'error' => 'Seuls les rendez-vous en attente peuvent être modifiés.'], 400);
        }

        $slotId = $request->request->get('slot_id');
        if (!$slotId) {
            return $this->json(['success' => false, 'error' => 'Veuillez sélectionner un créneau.'], 400);
        }

        $currentSlot = $rdv->getSlot();

        // If same slot, nothing to do
        if ((int) $slotId === ($currentSlot ? $currentSlot->getId() : null)) {
            $slot = $rdv->getSlot();
            return $this->json([
                'success' => true,
                'rdv' => [
                    'id' => $rdv->getId(),
                    'statut' => $rdv->getStatut(),
                    'slot_id' => $slot ? $slot->getId() : null,
                    'slot_start' => $slot ? $slot->getStartAt()->format('d/m/Y H:i') : '',
                    'slot_end' => $slot ? $slot->getEndAt()->format('d/m/Y H:i') : '',
                    'created_at' => $rdv->getCreatedAt()->format('d/m/Y H:i'),
                ],
            ]);
        }

        $newSlot = $this->entityManager->getRepository(AvailabilitySlot::class)->find((int) $slotId);
        if (!$newSlot) {
            return $this->json(['success' => false, 'error' => 'Créneau introuvable.'], 404);
        }
        if ($newSlot->isBooked()) {
            return $this->json(['success' => false, 'error' => 'Ce créneau est déjà réservé.'], 409);
        }

        // Free old slot, book new slot
        if ($currentSlot) {
            $currentSlot->setIsBooked(false);
        }
        $newSlot->setIsBooked(true);
        $rdv->setSlot($newSlot);

        $this->entityManager->flush();

        $slot = $rdv->getSlot();
        return $this->json([
            'success' => true,
            'rdv' => [
                'id' => $rdv->getId(),
                'statut' => $rdv->getStatut(),
                'slot_id' => $slot ? $slot->getId() : null,
                'slot_start' => $slot ? $slot->getStartAt()->format('d/m/Y H:i') : '',
                'slot_end' => $slot ? $slot->getEndAt()->format('d/m/Y H:i') : '',
                'created_at' => $rdv->getCreatedAt()->format('d/m/Y H:i'),
            ],
        ]);
    }

    /** AJAX: Update RDV status (instructor/admin only) */
    #[Route('/rendezvous/api/{id}/status', name: 'public_rendezvous_status', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function apiUpdateStatus(Request $request, RendezVous $rdv): JsonResponse
    {
        // Only instructors and admins can change status
        if (!$this->isGranted('ROLE_INSTRUCTOR') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'error' => 'Accès refusé.'], 403);
        }

        $statut = $request->request->get('statut');
        $allowed = [RendezVous::STATUS_CONFIRME, RendezVous::STATUS_REJETE];

        if (!in_array($statut, $allowed, true)) {
            return $this->json(['success' => false, 'error' => 'Statut invalide.'], 400);
        }

        $oldStatut = $rdv->getStatut();
        $rdv->setStatut($statut);

        // If rejected, free the slot
        if ($statut === RendezVous::STATUS_REJETE && $oldStatut !== RendezVous::STATUS_REJETE) {
            $rdv->getSlot()?->setIsBooked(false);
        }
        // If un-rejected (back to confirmed), re-book the slot
        if ($oldStatut === RendezVous::STATUS_REJETE && $statut !== RendezVous::STATUS_REJETE) {
            $rdv->getSlot()?->setIsBooked(true);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true, 'statut' => $rdv->getStatut()]);
    }

    // =============================================
    //  ADMIN ROUTES
    // =============================================

    #[Route('/admin/rendezvous/', name: 'admin_rendezvous_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $rendezvous = $this->entityManager->getRepository(RendezVous::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('rendez_vous/index.html.twig', [
            'rendezvous_list' => $rendezvous,
        ]);
    }

    #[Route('/admin/rendezvous/new', name: 'admin_rendezvous_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $availableSlots = $this->entityManager->getRepository(AvailabilitySlot::class)->findBy(
            ['isBooked' => false],
            ['startAt' => 'ASC']
        );

        $formData = [
            'slot_id' => $request->request->get('slot_id', ''),
            'statut'  => $request->request->get('statut', RendezVous::STATUS_EN_ATTENTE),
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            if (empty($formData['slot_id'])) {
                $errors['slot_id'] = 'Veuillez sélectionner un créneau.';
            }

            $slot = null;
            if (empty($errors)) {
                $slot = $this->entityManager->getRepository(AvailabilitySlot::class)->find((int) $formData['slot_id']);
                if (!$slot) {
                    $errors['slot_id'] = 'Créneau introuvable.';
                } elseif ($slot->isBooked()) {
                    $errors['slot_id'] = 'Ce créneau est déjà réservé.';
                }
            }

            if (empty($errors)) {
                $rdv = new RendezVous();
                $rdv->setSlot($slot);
                $rdv->setStatut(RendezVous::STATUS_EN_ATTENTE);
                $rdv->setOwnerToken(bin2hex(random_bytes(16)));
                $slot->setIsBooked(true);

                $this->entityManager->persist($rdv);
                $this->entityManager->flush();

                $this->addFlash('success', 'Rendez-vous créé avec succès.');
                return $this->redirectToRoute('admin_rendezvous_index');
            }

            $this->addFlash('error', 'Veuillez corriger les erreurs.');
        }

        return $this->render('rendez_vous/new.html.twig', [
            'form_data'       => $formData,
            'errors'          => $errors,
            'available_slots' => $availableSlots,
        ]);
    }

    #[Route('/admin/rendezvous/{id}', name: 'admin_rendezvous_show')]
    #[IsGranted('ROLE_ADMIN')]
    public function show(RendezVous $rdv): Response
    {
        return $this->render('rendez_vous/show.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    #[Route('/admin/rendezvous/{id}/edit', name: 'admin_rendezvous_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, RendezVous $rdv): Response
    {
        $availableSlots = $this->entityManager->getRepository(AvailabilitySlot::class)->findBy(
            ['isBooked' => false],
            ['startAt' => 'ASC']
        );

        $currentSlot = $rdv->getSlot();
        $slotInList = false;
        foreach ($availableSlots as $s) {
            if ($currentSlot && $s->getId() === $currentSlot->getId()) {
                $slotInList = true;
                break;
            }
        }
        if ($currentSlot && !$slotInList) {
            array_unshift($availableSlots, $currentSlot);
        }

        $formData = [
            'slot_id' => $request->isMethod('POST') ? $request->request->get('slot_id', '') : ($currentSlot ? $currentSlot->getId() : ''),
            'statut'  => $request->isMethod('POST') ? $request->request->get('statut', '') : $rdv->getStatut(),
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $allowedStatuses = [RendezVous::STATUS_EN_ATTENTE, RendezVous::STATUS_CONFIRME, RendezVous::STATUS_REJETE];
            if (!in_array($formData['statut'], $allowedStatuses, true)) {
                $errors['statut'] = 'Statut invalide.';
            }

            if (empty($formData['slot_id'])) {
                $errors['slot_id'] = 'Veuillez sélectionner un créneau.';
            }

            $newSlot = null;
            if (empty($errors)) {
                $newSlot = $this->entityManager->getRepository(AvailabilitySlot::class)->find((int) $formData['slot_id']);
                if (!$newSlot) {
                    $errors['slot_id'] = 'Créneau introuvable.';
                } elseif ($newSlot->getId() !== ($currentSlot ? $currentSlot->getId() : null) && $newSlot->isBooked()) {
                    $errors['slot_id'] = 'Ce créneau est déjà réservé.';
                }
            }

            if (empty($errors)) {
                $oldStatut = $rdv->getStatut();
                if ($currentSlot && (!$newSlot || $newSlot->getId() !== $currentSlot->getId())) {
                    $currentSlot->setIsBooked(false);
                }
                if ($newSlot && (!$currentSlot || $newSlot->getId() !== $currentSlot->getId())) {
                    $newSlot->setIsBooked(true);
                }
                $rdv->setSlot($newSlot);
                $rdv->setStatut($formData['statut']);
                if ($formData['statut'] === RendezVous::STATUS_REJETE && $oldStatut !== RendezVous::STATUS_REJETE) {
                    $rdv->getSlot()?->setIsBooked(false);
                }
                if ($oldStatut === RendezVous::STATUS_REJETE && $formData['statut'] !== RendezVous::STATUS_REJETE) {
                    $rdv->getSlot()?->setIsBooked(true);
                }

                $this->entityManager->flush();

                $this->addFlash('success', 'Rendez-vous mis à jour avec succès.');
                return $this->redirectToRoute('admin_rendezvous_show', ['id' => $rdv->getId()]);
            }

            $this->addFlash('error', 'Veuillez corriger les erreurs.');
        }

        return $this->render('rendez_vous/edit.html.twig', [
            'rdv'             => $rdv,
            'form_data'       => $formData,
            'errors'          => $errors,
            'available_slots' => $availableSlots,
        ]);
    }

    #[Route('/admin/rendezvous/{id}/delete', name: 'admin_rendezvous_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, RendezVous $rdv): Response
    {
        if ($this->isCsrfTokenValid('delete' . $rdv->getId(), $request->request->get('_token'))) {
            $slot = $rdv->getSlot();
            if ($slot) {
                $slot->setIsBooked(false);
            }
            $this->entityManager->remove($rdv);
            $this->entityManager->flush();
            $this->addFlash('success', 'Rendez-vous supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_rendezvous_index');
    }
}