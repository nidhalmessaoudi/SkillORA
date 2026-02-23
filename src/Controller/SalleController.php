<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\Salle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/salles')]
#[IsGranted('ROLE_ADMIN')]
class SalleController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('/', name: 'admin_salles_index')]
    public function index(): Response
    {
        $salles = $this->entityManager->getRepository(Salle::class)->findBy([], ['name' => 'ASC']);
        $reservations = $this->entityManager->getRepository(Reservation::class)->findBy([], ['dateReservation' => 'ASC']);
        $events = $this->entityManager->getRepository(Event::class)->findBy([], ['startDate' => 'ASC']);

        $durationBySalle = [];
        $eventById = [];
        foreach ($salles as $salle) {
            $durationBySalle[$salle->getId()] = $salle->getDuration() ?? 0;
        }
        foreach ($events as $event) {
            $eventById[$event->getId()] = $event;
        }

        $stats = [];
        $eventTitleBySalle = [];
        $eventStatsBySalle = [];
        foreach ($reservations as $reservation) {
            $salleId = $reservation->getSalleId();
            if (!$salleId) {
                continue;
            }
            if (!isset($stats[$salleId])) {
                $stats[$salleId] = [
                    'count' => 0,
                    'date_counts' => [],
                    'date_durations' => [],
                    'max_count' => 0,
                    'max_duration' => 0,
                    'event_dates' => [],
                ];
            }
            $stats[$salleId]['count']++;
            $eventId = $reservation->getEventId();
            if ($eventId) {
                if (!isset($eventStatsBySalle[$salleId][$eventId])) {
                    $eventStatsBySalle[$salleId][$eventId] = [
                        'event_id' => $eventId,
                        'event_title' => $eventById[$eventId]->getTitle() ?? 'Unknown',
                        'reservation_count' => 0,
                        'place_count' => 0,
                    ];
                }
                $eventStatsBySalle[$salleId][$eventId]['reservation_count']++;
                $seats = $this->parseSeats((string) $reservation->getNombrePlaces());
                $eventStatsBySalle[$salleId][$eventId]['place_count'] += count($seats);
            }
            $date = $reservation->getDateReservation();
            if ($date) {
                $key = $date->format('Y-m-d');
                $stats[$salleId]['date_counts'][$key] = ($stats[$salleId]['date_counts'][$key] ?? 0) + 1;
                if ($stats[$salleId]['date_counts'][$key] > $stats[$salleId]['max_count']) {
                    $stats[$salleId]['max_count'] = $stats[$salleId]['date_counts'][$key];
                }
                $duration = $durationBySalle[$salleId] ?? 0;
                $stats[$salleId]['date_durations'][$key] = ($stats[$salleId]['date_durations'][$key] ?? 0) + $duration;
                if ($stats[$salleId]['date_durations'][$key] > $stats[$salleId]['max_duration']) {
                    $stats[$salleId]['max_duration'] = $stats[$salleId]['date_durations'][$key];
                }
            }
        }

        foreach ($salles as $salle) {
            $salleId = $salle->getId();
            if (!isset($stats[$salleId])) {
                $stats[$salleId] = [
                    'count' => 0,
                    'date_counts' => [],
                    'date_durations' => [],
                    'max_count' => 0,
                    'max_duration' => 0,
                    'event_dates' => [],
                ];
            }

            $linkedEventTitle = null;
            $linkedEventId = $salle->getEventId();
            if ($linkedEventId && isset($eventById[$linkedEventId])) {
                $linkedEventTitle = $eventById[$linkedEventId]->getTitle();
            } else {
                foreach ($events as $event) {
                    if ($event->getSalleId() === $salleId) {
                        $linkedEventTitle = $event->getTitle();
                        break;
                    }
                }
            }
            $eventTitleBySalle[$salleId] = $linkedEventTitle;

            $eventDates = [];
            if ($linkedEventId && isset($eventById[$linkedEventId])) {
                $eventDates = array_merge($eventDates, $this->expandEventDates($eventById[$linkedEventId]));
            }

            foreach ($events as $event) {
                if ($event->getSalleId() === $salleId) {
                    $eventDates = array_merge($eventDates, $this->expandEventDates($event));
                }
            }

            $eventDates = array_values(array_unique($eventDates));
            $stats[$salleId]['event_dates'] = array_fill_keys($eventDates, true);
            if (!isset($eventStatsBySalle[$salleId])) {
                $eventStatsBySalle[$salleId] = [];
            }
        }

        $year = (int) date('Y');
        $calendar = $this->buildContributionCalendar($year);

        return $this->render('pages/admin/salles/index.html.twig', [
            'salles' => $salles,
            'stats' => $stats,
            'event_titles' => $eventTitleBySalle,
            'event_stats' => $eventStatsBySalle,
            'calendar_year' => $year,
            'calendar_weeks' => $calendar['weeks'],
            'calendar_month_labels' => $calendar['month_labels'],
        ]);
    }

    #[Route('/new', name: 'admin_salles_new')]
    public function new(Request $request): Response
    {
        $formData = $this->buildSalleFormData($request, null);
        $events = $this->entityManager->getRepository(Event::class)->findBy([], ['startDate' => 'DESC']);
        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validateSalleForm($formData);
            $modelPath = $this->handleModelUpload($request);

            if (empty($errors)) {
                $salle = new Salle();
                $salle->setName($formData['name']);
                $salle->setImage3d($formData['image_3d'] ?: null);
                $salle->setMaxParticipants((int) $formData['max_participants']);
                $salle->setDuration((int) $formData['duration']);
                $salle->setEquipment($formData['equipment'] ?: null);
                $salle->setLocation($formData['location']);
                $salle->setImage3d($modelPath);
                $salle->setEventId($formData['event_id'] !== '' ? (int) $formData['event_id'] : null);

                $this->entityManager->persist($salle);
                $this->entityManager->flush();

                $this->addFlash('success', 'Salle created successfully.');
                return $this->redirectToRoute('admin_salles_show', ['id' => $salle->getId()]);
            }

            $this->addFlash('error', 'Please fill in all required fields.');
        }

        return $this->render('pages/admin/salles/new.html.twig', [
            'form_data' => $formData,
            'events' => $events,
            'errors' => $errors,
        ]);
    }

    #[Route('/{id}', name: 'admin_salles_show')]
    public function show(Salle $salle): Response
    {
        $reservations = $this->entityManager->getRepository(Reservation::class)->findBy([
            'salleId' => $salle->getId(),
        ], ['dateReservation' => 'ASC']);

        $reservedDates = [];
        foreach ($reservations as $reservation) {
            $date = $reservation->getDateReservation();
            if ($date) {
                $reservedDates[] = $date->format('Y-m-d');
            }
        }
        $reservedDates = array_values(array_unique($reservedDates));
        $reservedMap = array_fill_keys($reservedDates, true);

        $year = (int) date('Y');
        $calendarMonths = $this->buildCalendarMonths($year);

        return $this->render('pages/admin/salles/show.html.twig', [
            'salle' => $salle,
            'reservation_count' => count($reservations),
            'reserved_dates' => $reservedDates,
            'reserved_map' => $reservedMap,
            'calendar_year' => $year,
            'calendar_months' => $calendarMonths,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_salles_edit')]
    public function edit(Request $request, Salle $salle): Response
    {
        $formData = $this->buildSalleFormData($request, $salle);
        $events = $this->entityManager->getRepository(Event::class)->findBy([], ['startDate' => 'DESC']);
        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validateSalleForm($formData);
            $modelPath = $this->handleModelUpload($request);

            if (empty($errors)) {
                $salle->setName($formData['name']);
                $salle->setImage3d($formData['image_3d'] ?: null);
                $salle->setMaxParticipants((int) $formData['max_participants']);
                $salle->setDuration((int) $formData['duration']);
                $salle->setEquipment($formData['equipment'] ?: null);
                $salle->setLocation($formData['location']);
                if ($modelPath) {
                    $salle->setImage3d($modelPath);
                }
                $salle->setEventId($formData['event_id'] !== '' ? (int) $formData['event_id'] : null);

                $this->entityManager->flush();

                $this->addFlash('success', 'Salle updated successfully.');
                return $this->redirectToRoute('admin_salles_show', ['id' => $salle->getId()]);
            }

            $this->addFlash('error', 'Please fill in all required fields.');
        }

        return $this->render('pages/admin/salles/edit.html.twig', [
            'salle' => $salle,
            'form_data' => $formData,
            'events' => $events,
            'errors' => $errors,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_salles_delete', methods: ['POST'])]
    public function delete(Salle $salle): Response
    {
        $eventRepo = $this->entityManager->getRepository(Event::class);
        $reservationRepo = $this->entityManager->getRepository(Reservation::class);

        $eventIds = [];
        $linkedEventId = $salle->getEventId();
        if ($linkedEventId) {
            $eventIds[] = $linkedEventId;
        }

        $eventsForSalle = $eventRepo->findBy(['salleId' => $salle->getId()]);
        foreach ($eventsForSalle as $event) {
            $eventIds[] = $event->getId();
        }

        $eventIds = array_values(array_unique(array_filter($eventIds)));

        if (!empty($eventIds)) {
            $reservationsByEvent = $reservationRepo->findBy(['eventId' => $eventIds]);
            foreach ($reservationsByEvent as $reservation) {
                $this->entityManager->remove($reservation);
            }
        }

        $reservationsBySalle = $reservationRepo->findBy(['salleId' => $salle->getId()]);
        foreach ($reservationsBySalle as $reservation) {
            $this->entityManager->remove($reservation);
        }

        if (!empty($eventsForSalle)) {
            foreach ($eventsForSalle as $event) {
                $this->entityManager->remove($event);
            }
        }

        if ($linkedEventId) {
            $linkedEvent = $eventRepo->find($linkedEventId);
            if ($linkedEvent) {
                $this->entityManager->remove($linkedEvent);
            }
        }

        $this->entityManager->remove($salle);
        $this->entityManager->flush();

        $this->addFlash('success', 'Salle deleted successfully.');
        return $this->redirectToRoute('admin_salles_index');
    }

    private function buildSalleFormData(Request $request, ?Salle $salle): array
    {
        if ($request->isMethod('POST')) {
            $eventId = (string) $request->request->get('event_id', '');
            $eventTitle = '';
            if ($eventId !== '') {
                $event = $this->entityManager->getRepository(Event::class)->find((int) $eventId);
                $eventTitle = $event?->getTitle() ?? '';
            }

            return [
                'name' => (string) $request->request->get('name', ''),
                'image_3d' => (string) $request->request->get('image_3d', ''),
                'max_participants' => (string) $request->request->get('max_participants', ''),
                'duration' => (string) $request->request->get('duration', ''),
                'equipment' => (string) $request->request->get('equipment', ''),
                'location' => (string) $request->request->get('location', ''),
                'event_id' => $eventId,
                'event_title' => $eventTitle,
            ];
        }

        $linkedEventId = $salle?->getEventId();
        $linkedEventTitle = '';
        $linkedEvent = null;
        if ($linkedEventId) {
            $linkedEvent = $this->entityManager->getRepository(Event::class)->find($linkedEventId);
        } elseif ($salle) {
            $linkedEvent = $this->entityManager->getRepository(Event::class)->findOneBy([
                'salleId' => $salle->getId(),
            ]);
            if ($linkedEvent) {
                $linkedEventId = $linkedEvent->getId();
            }
        }
        if ($linkedEvent) {
            $linkedEventTitle = $linkedEvent->getTitle() ?? '';
        }

        return [
            'name' => $salle?->getName() ?? '',
            'image_3d' => $salle?->getImage3d() ?? '',
            'max_participants' => $salle?->getMaxParticipants() ?? '',
            'duration' => $salle?->getDuration() ?? '',
            'equipment' => $salle?->getEquipment() ?? '',
            'location' => $salle?->getLocation() ?? '',
            'event_id' => $linkedEventId ?? '',
            'event_title' => $linkedEventTitle,
        ];
    }

    private function validateSalleForm(array $data): array
    {
        $required = [
            'name' => 'Champ obligatoire.',
            'location' => 'Champ obligatoire.',
            'max_participants' => 'Champ obligatoire.',
            'duration' => 'Champ obligatoire.',
        ];

        $errors = [];
        foreach ($required as $field => $message) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = $message;
            }
        }

        return $errors;
    }

    private function handleModelUpload(Request $request): ?string
    {
        $file = $request->files->get('image_3d');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return null;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['glb', 'gltf'], true)) {
            $this->addFlash('error', 'Only .glb or .gltf files are allowed.');
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/salles';

        try {
            $file->move($targetDir, $filename);
        } catch (FileException) {
            $this->addFlash('error', '3D upload failed. Please try again.');
            return null;
        }

        return '/uploads/salles/' . $filename;
    }

    private function parseSeats(string $seats): array
    {
        if ($seats === '') {
            return [];
        }

        $values = array_filter(array_map('trim', explode(',', $seats)), static fn ($value) => $value !== '');
        $numbers = [];
        foreach ($values as $value) {
            if (!ctype_digit($value)) {
                continue;
            }
            $numbers[] = (int) $value;
        }

        $numbers = array_values(array_unique($numbers));
        sort($numbers);
        return $numbers;
    }

    private function buildCalendarMonths(int $year): array
    {
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $first = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
            $months[] = [
                'number' => $month,
                'name' => $first->format('F'),
                'days' => (int) $first->format('t'),
                'first_weekday' => (int) $first->format('N'),
            ];
        }

        return $months;
    }

    private function buildContributionCalendar(int $year): array
    {
        $start = new \DateTime(sprintf('%04d-01-01', $year));
        $start->modify('Sunday this week');

        $end = new \DateTime(sprintf('%04d-12-31', $year));
        $end->modify('Saturday this week');

        $weeks = [];
        $monthLabels = [];
        $current = clone $start;
        $weekIndex = 0;

        while ($current <= $end) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $current->format('Y-m-d');
                if ($current->format('j') === '1' && !isset($monthLabels[$weekIndex])) {
                    $monthLabels[$weekIndex] = $current->format('M');
                }
                $current->modify('+1 day');
            }
            $weeks[] = $week;
            $weekIndex++;
        }

        return [
            'weeks' => $weeks,
            'month_labels' => $monthLabels,
        ];
    }

    private function expandEventDates(Event $event): array
    {
        $start = $event->getStartDate();
        $end = $event->getEndDate();
        if (!$start || !$end) {
            return [];
        }

        $dates = [];
        $current = (clone $start)->setTime(0, 0, 0);
        $endDate = (clone $end)->setTime(0, 0, 0);

        while ($current <= $endDate) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }
}
