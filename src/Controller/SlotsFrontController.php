<?php

namespace App\Controller;

use App\Entity\AvailabilitySlot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SlotsFrontController extends AbstractController
{
    #[Route('/slots/front', name: 'slots_front')]
    public function front(EntityManagerInterface $entityManager): Response
    {
        $slots = $entityManager->getRepository(AvailabilitySlot::class)->findBy([], ['startAt' => 'DESC']);
        return $this->render('slots/front.html.twig', [
            'slots' => $slots,
        ]);
    }
}
