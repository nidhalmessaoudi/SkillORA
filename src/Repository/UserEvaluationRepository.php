<?php

namespace App\Repository;

use App\Entity\UserEvaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserEvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEvaluation::class);
    }

    /**
     * Vérifie si l'utilisateur a déjà passé une évaluation
     */
    public function findByUserAndEvaluation($user, $evaluation): ?UserEvaluation
    {
        return $this->createQueryBuilder('ue')
            ->andWhere('ue.user = :user')
            ->andWhere('ue.evaluation = :evaluation')
            ->setParameters([
                'user' => $user,
                'evaluation' => $evaluation,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
