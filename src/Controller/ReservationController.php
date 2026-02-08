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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reservations')]
#[IsGranted('ROLE_ADMIN')]
class ReservationController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('/', name: 'admin_reservations_index')]
    public function index(): Response
    {
        $reservations = $this->entityManager->getRepository(Reservation::class)->findBy([], ['dateReservation' => 'DESC']);
        $events = $this->entityManager->getRepository(Event::class)->findAll();
        $salles = $this->entityManager->getRepository(Salle::class)->findAll();

        $eventMap = [];
        foreach ($events as $event) {
            $eventMap[$event->getId()] = $event;
        }

        $salleMap = [];
        foreach ($salles as $salle) {
            $salleMap[$salle->getId()] = $salle;
        }

        return $this->render('pages/admin/reservations/index.html.twig', [
            'reservations' => $reservations,
            'reservation_total' => count($reservations),
            'event_map' => $eventMap,
            'salle_map' => $salleMap,
        ]);
    }

    #[Route('/new', name: 'admin_reservations_new')]
    public function new(Request $request): Response
    {
        $events = $this->entityManager->getRepository(Event::class)->findBy([], ['startDate' => 'DESC']);
        $salles = $this->entityManager->getRepository(Salle::class)->findBy([], ['name' => 'ASC']);

        $formData = $this->buildReservationFormData($request, null);

        if ($request->isMethod('POST')) {
            $missing = $this->validateReservationForm($formData);
            $dateReservation = $this->parseDate($formData['date_reservation'] ?? null);

            if (!$dateReservation) {
                $missing[] = 'date_reservation';
            }

            if (empty($missing)) {
                $reservation = new Reservation();
                $reservation->setEventId((int) $formData['event_id']);
                $reservation->setSalleId((int) $formData['salle_id']);
                $reservation->setNom($formData['nom']);
                $reservation->setPrenom($formData['prenom']);
                $reservation->setTelephone($formData['telephone']);
                $reservation->setAdresse($formData['adresse'] ?: null);
                $reservation->setNombrePlaces((string) $formData['nombre_places']);
                $reservation->setDateReservation($dateReservation);
                $reservation->setUserId($formData['user_id'] !== '' ? (int) $formData['user_id'] : null);

                $this->entityManager->persist($reservation);
                $this->entityManager->flush();

                $this->addFlash('success', 'Reservation created successfully.');
                return $this->redirectToRoute('admin_reservations_show', ['id' => $reservation->getId()]);
            }

            $this->addFlash('error', 'Please fill in all required fields.');
        }

       
    }

    #[Route('/{id}', name: 'admin_reservations_show')]
    public function show(Reservation $reservation): Response
    {
        $event = null;
        if ($reservation->getEventId()) {
            $event = $this->entityManager->getRepository(Event::class)->find($reservation->getEventId());
        }

        $salle = null;
        if ($reservation->getSalleId()) {
            $salle = $this->entityManager->getRepository(Salle::class)->find($reservation->getSalleId());
        }

        return $this->render('pages/admin/reservations/show.html.twig', [
            'reservation' => $reservation,
            'event' => $event,
            'salle' => $salle,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_reservations_edit')]
    public function edit(Request $request, Reservation $reservation): Response
    {
        $events = $this->entityManager->getRepository(Event::class)->findBy([], ['startDate' => 'DESC']);
        $salles = $this->entityManager->getRepository(Salle::class)->findBy([], ['name' => 'ASC']);

        $formData = $this->buildReservationFormData($request, $reservation);

        if ($request->isMethod('POST')) {
            $missing = $this->validateReservationForm($formData);
            $dateReservation = $this->parseDate($formData['date_reservation'] ?? null);

            if (!$dateReservation) {
                $missing[] = 'date_reservation';
            }

            if (empty($missing)) {
                $reservation->setEventId((int) $formData['event_id']);
                $reservation->setSalleId((int) $formData['salle_id']);
                $reservation->setNom($formData['nom']);
                $reservation->setPrenom($formData['prenom']);
                $reservation->setTelephone($formData['telephone']);
                $reservation->setAdresse($formData['adresse'] ?: null);
                $reservation->setNombrePlaces((string) $formData['nombre_places']);
                $reservation->setDateReservation($dateReservation);
                $reservation->setUserId($formData['user_id'] !== '' ? (int) $formData['user_id'] : null);

                $this->entityManager->flush();

                $this->addFlash('success', 'Reservation updated successfully.');
                return $this->redirectToRoute('admin_reservations_show', ['id' => $reservation->getId()]);
            }

            $this->addFlash('error', 'Please fill in all required fields.');
        }

        return $this->render('pages/admin/reservations/edit.html.twig', [
            'reservation' => $reservation,
            'form_data' => $formData,
            'events' => $events,
            'salles' => $salles,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_reservations_delete', methods: ['POST'])]
    public function delete(Reservation $reservation): Response
    {
        $salle = null;
        if ($reservation->getSalleId()) {
            $salle = $this->entityManager->getRepository(Salle::class)->find($reservation->getSalleId());
        }

        $this->entityManager->remove($reservation);
        if ($salle) {
            $this->entityManager->remove($salle);
        }
        $this->entityManager->flush();

        $this->addFlash('success', 'Reservation deleted successfully.');
        return $this->redirectToRoute('admin_reservations_index');
    }

    private function buildReservationFormData(Request $request, ?Reservation $reservation): array
    {
        if ($request->isMethod('POST')) {
            return [
                'event_id' => (string) $request->request->get('event_id', ''),
                'salle_id' => (string) $request->request->get('salle_id', ''),
                'nom' => (string) $request->request->get('nom', ''),
                'prenom' => (string) $request->request->get('prenom', ''),
                'telephone' => (string) $request->request->get('telephone', ''),
                'adresse' => (string) $request->request->get('adresse', ''),
                'nombre_places' => (string) $request->request->get('nombre_places', ''),
                'date_reservation' => (string) $request->request->get('date_reservation', ''),
                'user_id' => (string) $request->request->get('user_id', ''),
            ];
        }

        return [
            'event_id' => $reservation?->getEventId() ?? '',
            'salle_id' => $reservation?->getSalleId() ?? '',
            'nom' => $reservation?->getNom() ?? '',
            'prenom' => $reservation?->getPrenom() ?? '',
            'telephone' => $reservation?->getTelephone() ?? '',
            'adresse' => $reservation?->getAdresse() ?? '',
            'nombre_places' => $reservation?->getNombrePlaces() ?? '',
            'date_reservation' => $reservation?->getDateReservation()?->format('Y-m-d\TH:i') ?? '',
            'user_id' => $reservation?->getUserId() ?? '',
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
            'nombre_places',
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
}
