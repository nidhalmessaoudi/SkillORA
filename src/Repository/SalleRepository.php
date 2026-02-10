<?php

namespace App\Repository;

use App\Entity\Salle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Salle>
 */
class SalleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Salle::class);
    }

    public function add(Salle $salle, bool $flush = false): void
    {
        $this->getEntityManager()->persist($salle);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Salle $salle, bool $flush = false): void
    {
        $this->getEntityManager()->remove($salle);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
