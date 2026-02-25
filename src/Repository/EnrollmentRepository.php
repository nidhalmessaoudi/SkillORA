<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Enrollment>
 */
class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    public function findOneByUserAndCourse(User $user, Course $course): ?Enrollment
    {
        return $this->findOneBy([
            'user' => $user,
            'course' => $course,
        ]);
    }

    /**
     * @return Enrollment[]
     */
    public function findByUserWithCourse(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.course', 'c')
            ->addSelect('c')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.enrolledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByCourse(Course $course): int
    {
        $count = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.course = :course')
            ->setParameter('course', $course)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function countCompletedByCourse(Course $course): int
    {
        $count = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.course = :course')
            ->andWhere('e.status = :completedStatus OR e.progressPercent >= 100')
            ->setParameter('course', $course)
            ->setParameter('completedStatus', Enrollment::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
