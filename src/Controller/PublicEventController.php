<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Salle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicEventController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('/events', name: 'events_index')]
    public function index(): Response
    {
        $events = $this->entityManager->getRepository(Event::class)->findBy([], ['startDate' => 'ASC']);
        $salles = $this->entityManager->getRepository(Salle::class)->findAll();

        $eventTypes = [];
        foreach ($events as $event) {
            $type = $event->getEventType();
            if (!$type) {
                continue;
            }
            $eventTypes[$type] = $type;
        }
        $eventTypes = array_values($eventTypes);
        sort($eventTypes, SORT_NATURAL | SORT_FLAG_CASE);

        $salleMap = [];
        foreach ($salles as $salle) {
            $salleMap[$salle->getId()] = $salle;
        }

        return $this->render('pages/events/index.html.twig', [
            'events' => $events,
            'salle_map' => $salleMap,
            'event_types' => $eventTypes,
        ]);
    }
}
