<?php

namespace App\Repository;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Answer::class);
    }

    public function getEvaluationStatsForUser(User $user, Evaluation $evaluation): array
    {
        // 1) Total questions in this evaluation
        $totalQuestions = (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(q.id)')
            ->from('App\Entity\Question', 'q')
            ->where('q.evaluation = :evaluation')
            ->setParameter('evaluation', $evaluation)
            ->getQuery()
            ->getSingleScalarResult();

        // 2) Correct answers by this user (distinct questions to avoid duplicates)
        $correctAnswers = (int) $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT q.id)')
            ->join('a.question', 'q')
            ->where('a.student = :user')
            ->andWhere('q.evaluation = :evaluation')
            ->andWhere('a.isCorrect = true')
            ->setParameter('user', $user)
            ->setParameter('evaluation', $evaluation)
            ->getQuery()
            ->getSingleScalarResult();

        // 3) Accuracy %
        $accuracy = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;

        return [
            'totalQuestions' => $totalQuestions,
            'correctAnswers' => $correctAnswers,
            'accuracy'       => $accuracy,
        ];
    }
}
