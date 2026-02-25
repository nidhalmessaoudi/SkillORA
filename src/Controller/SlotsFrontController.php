<?php

namespace App\Controller;

use App\Entity\AvailabilitySlot;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SlotsFrontController extends AbstractController
{
    private function isStudentUser(): bool
    {
        return $this->isGranted('ROLE_USER')
            && !$this->isGranted('ROLE_PROFESSOR')
            && !$this->isGranted('ROLE_ADMIN');
    }

    #[Route('/slots/front', name: 'slots_front')]
    public function front(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirect('/login');
        }

        if (
            !$this->isGranted('ROLE_USER')
            && !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('ROLE_PROFESSOR')
        ) {
            throw $this->createAccessDeniedException();
        }

        $repository = $entityManager->getRepository(AvailabilitySlot::class);
        $baseQb = $repository->createQueryBuilder('s');

        if ($this->isGranted('ROLE_PROFESSOR') && !$this->isGranted('ROLE_ADMIN')) {
            $baseQb
                ->andWhere('s.professor = :professor')
                ->setParameter('professor', $this->getUser());
        }

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 9;

        $countQb = clone $baseQb;
        $totalItems = (int) $countQb
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($page, $totalPages);

        $itemsQb = clone $baseQb;
        $itemsQb
            ->orderBy('s.startAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $slots = (new Paginator($itemsQb->getQuery(), true))->getIterator()->getArrayCopy();

        $availableCount = (clone $baseQb)
            ->select('COUNT(s.id)')
            ->andWhere('s.isBooked = false')
            ->getQuery()
            ->getSingleScalarResult();

        $bookedCount = (clone $baseQb)
            ->select('COUNT(s.id)')
            ->andWhere('s.isBooked = true')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('slots/front.html.twig', [
            'pagination' => [
                'items' => $slots,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage,
                'total_items' => $totalItems,
            ],
            'total_slots' => $totalItems,
            'available_slots_count' => $availableCount,
            'booked_slots_count' => $bookedCount,
        ]);
    }

   #[Route('/slots/calendar', name: 'slots_calendar', methods: ['GET'])]
public function calendar(): Response
{
    if (!$this->getUser()) {
        return $this->redirect('/login');
    }

    if (!$this->isStudentUser()) {
        throw $this->createAccessDeniedException();
    }

    return $this->render('slots/calendar.html.twig', [
        'google_calendar_api_key' => $_ENV['GOOGLE_CALENDAR_API_KEY'] ?? '',
    ]);
}

    #[Route('/slots/calendar/api', name: 'slots_calendar_api', methods: ['GET'])]
    public function calendarApi(EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirect('/login');
        }

        if (!$this->isStudentUser()) {
            throw $this->createAccessDeniedException();
        }

        $slots = $entityManager
            ->getRepository(AvailabilitySlot::class)
            ->findBy([], ['startAt' => 'ASC']);

        $events = [];
        foreach ($slots as $slot) {
            $isBooked = $slot->isBooked();

            $events[] = [
                'id' => $slot->getId(),
                'title' => $isBooked ? 'Réservé' : 'Disponible',
                'start' => $slot->getStartAt()->format('Y-m-d\TH:i:s'),
                'end' => $slot->getEndAt()->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $isBooked ? '#ef4444' : '#22c55e',
                'borderColor' => $isBooked ? '#dc2626' : '#16a34a',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'isBooked' => $isBooked,
                    'professor' => $slot->getProfessor() ? $slot->getProfessor()->getFullName() : null,
                    'locationLabel' => $slot->getLocationLabel(),
                ],
            ];
        }

        return $this->json($events);
    }
}