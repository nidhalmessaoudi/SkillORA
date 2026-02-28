<?php

namespace App\Repository;

use App\Entity\Evaluation;
use App\Entity\User;
use App\Entity\UserEvaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEvaluation>
 */
class UserEvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEvaluation::class);
    }

    /**
     * Vérifie si l'utilisateur a déjà passé une évaluation
     */
    public function findByUserAndEvaluation(User $user, Evaluation $evaluation): ?UserEvaluation
    {
        return $this->createQueryBuilder('ue')
            ->andWhere('ue.user = :user')
            ->andWhere('ue.evaluation = :evaluation')
            ->setParameter('user', $user)
            ->setParameter('evaluation', $evaluation)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
