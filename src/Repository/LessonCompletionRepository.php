<?php

namespace App\Repository;

use App\Entity\Enrollment;
use App\Entity\Lesson;
use App\Entity\LessonCompletion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LessonCompletion>
 */
class LessonCompletionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonCompletion::class);
    }

    public function existsForEnrollmentAndLesson(Enrollment $enrollment, Lesson $lesson): bool
    {
        $count = $this->createQueryBuilder('lc')
            ->select('COUNT(lc.id)')
            ->where('lc.enrollment = :enrollment')
            ->andWhere('lc.lesson = :lesson')
            ->setParameter('enrollment', $enrollment)
            ->setParameter('lesson', $lesson)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function countByEnrollment(Enrollment $enrollment): int
    {
        $count = $this->createQueryBuilder('lc')
            ->select('COUNT(lc.id)')
            ->where('lc.enrollment = :enrollment')
            ->setParameter('enrollment', $enrollment)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return int[]
     */
    public function findCompletedLessonIdsForEnrollment(Enrollment $enrollment): array
    {
        $rows = $this->createQueryBuilder('lc')
            ->select('IDENTITY(lc.lesson) AS lessonId')
            ->where('lc.enrollment = :enrollment')
            ->setParameter('enrollment', $enrollment)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row): int => (int) $row['lessonId'], $rows));
    }
}
