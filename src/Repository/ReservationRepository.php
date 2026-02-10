<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function add(Reservation $reservation, bool $flush = false): void
    {
        $this->getEntityManager()->persist($reservation);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reservation $reservation, bool $flush = false): void
    {
        $this->getEntityManager()->remove($reservation);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
