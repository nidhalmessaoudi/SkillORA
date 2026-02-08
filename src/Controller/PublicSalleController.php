<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\Salle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicSalleController extends AbstractController
{
	public function __construct(private EntityManagerInterface $entityManager) {}

	#[Route('/salles', name: 'salles_index')]
	public function index(): Response
	{
		$salles = $this->entityManager->getRepository(Salle::class)->findBy([], ['name' => 'ASC']);
		$reservations = $this->entityManager->getRepository(Reservation::class)->findBy([], ['id' => 'ASC']);

		$reservedSeats = [];
		foreach ($reservations as $reservation) {
			$salleId = $reservation->getSalleId();
			$seatList = $reservation->getNombrePlaces();
			if (!$salleId || !$seatList) {
				continue;
			}
			$seats = $this->parseSeats($seatList);
			foreach ($seats as $seat) {
				$reservedSeats[$salleId][] = $seat;
			}
		}

		return $this->render('pages/salles/index.html.twig', [
			'salles' => $salles,
			'reserved_seats' => $reservedSeats,
		]);
	}

	#[Route('/salles/{id}', name: 'salles_show')]
	public function show(Salle $salle): Response
	{
		$event = null;
		if ($salle->getEventId()) {
			$event = $this->entityManager->getRepository(Event::class)->find($salle->getEventId());
		}
		if (!$event) {
			$event = $this->entityManager->getRepository(Event::class)->findOneBy([
				'salleId' => $salle->getId(),
			]);
		}

		$reservations = $this->entityManager->getRepository(Reservation::class)->findBy([
			'salleId' => $salle->getId(),
		]);

		$reservedSeats = [];
		foreach ($reservations as $reservation) {
			$seatList = $reservation->getNombrePlaces();
			if (!$seatList) {
				continue;
			}
			$seats = $this->parseSeats($seatList);
			foreach ($seats as $seat) {
				$reservedSeats[] = $seat;
			}
		}

		return $this->render('pages/salles/show.html.twig', [
			'salle' => $salle,
			'event' => $event,
			'reserved_seats' => $reservedSeats,
		]);
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
