<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\Salle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicReservationController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('/reservations/new', name: 'reservations_new')]
    public function new(Request $request): Response
    {
        $events = $this->entityManager->getRepository(Event::class)->findBy([], ['startDate' => 'ASC']);
        $salles = $this->entityManager->getRepository(Salle::class)->findBy([], ['name' => 'ASC']);

        $formData = $this->buildReservationFormData($request);
        $errors = [];

        if ($request->isMethod('POST')) {
            $missing = $this->validateReservationForm($formData);
            $dateReservation = $this->parseDate($formData['date_reservation'] ?? null);
            $seats = $this->parseSeats((string) ($formData['seats'] ?? ''));
            if (empty($seats)) {
                $seats = $this->parseSeats((string) ($formData['nombre_places'] ?? ''));
            }
            $salleId = (int) ($formData['salle_id'] ?? 0);
            $maxParticipants = $this->getSalleMaxParticipants($salleId);

            if (!$dateReservation) {
                $dateReservation = new \DateTime();
            }

            if (empty($seats)) {
                $missing[] = 'nombre_places';
                $errors['nombre_places'] = 'Champ obligatoire.';
                $this->addFlash('error', 'Please select at least one seat.');
            }

            foreach ($seats as $seat) {
                if ($maxParticipants !== null && ($seat < 1 || $seat > $maxParticipants)) {
                    $missing[] = 'nombre_places';
                    $errors['nombre_places'] = 'Champ obligatoire.';
                    $this->addFlash('error', 'Selected seat is out of range.');
                    break;
                }

                if ($salleId > 0 && $this->isSeatReserved($salleId, $seat)) {
                    $missing[] = 'nombre_places';
                    $errors['nombre_places'] = 'Champ obligatoire.';
                    $this->addFlash('error', 'One or more seats are already reserved.');
                    break;
                }
            }

            if (!empty($missing)) {
                $messages = [
                    'event_id' => 'Champ obligatoire.',
                    'salle_id' => 'Champ obligatoire.',
                    'prenom' => 'Champ obligatoire.',
                    'nom' => 'Champ obligatoire.',
                    'telephone' => 'Champ obligatoire.',
                    'nombre_places' => 'Champ obligatoire.',
                ];
                foreach ($missing as $field) {
                    if (isset($messages[$field])) {
                        $errors[$field] = $messages[$field];
                    }
                }
            }

            if (empty($missing)) {
                $reservation = new Reservation();
                $reservation->setEventId((int) $formData['event_id']);
                $reservation->setSalleId($salleId);
                $reservation->setNom($formData['nom']);
                $reservation->setPrenom($formData['prenom']);
                $reservation->setTelephone($formData['telephone']);
                $reservation->setAdresse($formData['adresse'] ?: null);
                $reservation->setNombrePlaces(implode(',', $seats));
                $reservation->setDateReservation($dateReservation);

                $user = $this->getUser();
                if ($user && method_exists($user, 'getId')) {
                    $reservation->setUserId((int) $user->getId());
                }

                $this->entityManager->persist($reservation);
                $this->entityManager->flush();

                $this->addFlash('success', 'Reservation submitted successfully.');
                return $this->redirectToRoute('events_index');
            }

            $this->addFlash('error', 'Please fill in all required fields.');
        }

        return $this->render('pages/reservations/new.html.twig', [
            'form_data' => $formData,
            'events' => $events,
            'salles' => $salles,
            'errors' => $errors,
        ]);
    }

    private function buildReservationFormData(Request $request): array
    {
        if ($request->isMethod('POST')) {
            $now = new \DateTime();
            $dateReservation = (string) $request->request->get('date_reservation', '');
            if ($dateReservation === '') {
                $dateReservation = $now->format('Y-m-d\\TH:i');
            }

            return [
                'event_id' => (string) $request->request->get('event_id', ''),
                'salle_id' => (string) $request->request->get('salle_id', ''),
                'nom' => (string) $request->request->get('nom', ''),
                'prenom' => (string) $request->request->get('prenom', ''),
                'telephone' => (string) $request->request->get('telephone', ''),
                'adresse' => (string) $request->request->get('adresse', ''),
                'nombre_places' => (string) $request->request->get('nombre_places', ''),
                'seats' => (string) $request->request->get('seats', ''),
                'max_participants' => (string) $request->request->get('max_participants', ''),
                'date_reservation' => $dateReservation,
                'seat_locked' => (bool) $request->request->get('seat_locked', false),
            ];
        }

        $now = new \DateTime();
        $salleId = (string) $request->query->get('salleId', '');
        $eventId = (string) $request->query->get('eventId', '');
        if ($eventId === '' && $salleId !== '') {
            $eventId = $this->resolveEventIdFromSalle((int) $salleId);
        }

        $adresse = '';
        if ($salleId !== '') {
            $salle = $this->entityManager->getRepository(Salle::class)->find((int) $salleId);
            $adresse = $salle?->getLocation() ?? '';
        }

        return [
            'event_id' => $eventId,
            'salle_id' => $salleId,
            'nom' => '',
            'prenom' => '',
            'telephone' => '',
            'adresse' => $adresse,
            'nombre_places' => (string) $request->query->get('seat', ''),
            'seats' => (string) $request->query->get('seats', ''),
            'max_participants' => (string) $request->query->get('maxParticipants', ''),
            'date_reservation' => $now->format('Y-m-d\\TH:i'),
            'seat_locked' => $request->query->get('seat', '') !== '' || $request->query->get('seats', '') !== '',
        ];
    }

    private function validateReservationForm(array $data): array
    {
        $required = [
            'event_id',
            'salle_id',
            'nom',
            'prenom',
            'telephone',
        ];

        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    private function parseDate(?string $value): ?\DateTimeInterface
    {
        if (!$value) {
            return null;
        }

        try {
            return new \DateTime($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function isSeatReserved(int $salleId, int $seatNumber): bool
    {
        $reservations = $this->entityManager->getRepository(Reservation::class)->findBy([
            'salleId' => $salleId,
        ]);

        foreach ($reservations as $reservation) {
            $seats = $this->parseSeats((string) $reservation->getNombrePlaces());
            if (in_array($seatNumber, $seats, true)) {
                return true;
            }
        }

        return false;
    }

    private function getSalleMaxParticipants(int $salleId): ?int
    {
        if ($salleId <= 0) {
            return null;
        }

        $salle = $this->entityManager->getRepository(Salle::class)->find($salleId);
        return $salle?->getMaxParticipants();
    }

    private function resolveEventIdFromSalle(int $salleId): string
    {
        if ($salleId <= 0) {
            return '';
        }

        $salle = $this->entityManager->getRepository(Salle::class)->find($salleId);
        if ($salle && $salle->getEventId()) {
            return (string) $salle->getEventId();
        }

        $event = $this->entityManager->getRepository(Event::class)->findOneBy([
            'salleId' => $salleId,
        ]);

        return $event ? (string) $event->getId() : '';
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
}
