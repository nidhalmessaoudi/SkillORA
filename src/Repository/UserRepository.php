<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmail(string $email): ?User
    {
        $user = $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        if ($user) {
            // Load roles from user_roles table
            $roles = $this->getEntityManager()->getConnection()
                ->fetchFirstColumn('SELECT role FROM user_roles WHERE user_id = ?', [$user->getId()]);
            if (!empty($roles)) {
                $user->setDbRoles($roles);
                $user->setRole((string) $roles[0]);
            }
        }

        return $user;
    }

    public function findAllWithStats(): array
    {
        $users = $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Load roles for all users
        foreach ($users as $user) {
            $roles = $this->getEntityManager()->getConnection()
                ->fetchFirstColumn('SELECT role FROM user_roles WHERE user_id = ?', [$user->getId()]);
            if (!empty($roles)) {
                $user->setDbRoles($roles);
                $user->setRole((string) $roles[0]);
            }
        }

        return $users;
    }

    public function getUserStats(): array
    {
        $totalUsers = $this->count([]);
        $bannedUsers = $this->count(['isActive' => false]);
        $activeUsers = $totalUsers - $bannedUsers;
        
        // Get users registered in the last 7 days
        $sevenDaysAgo = new \DateTime('-7 days');
        $newUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :date')
            ->setParameter('date', $sevenDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'banned' => $bannedUsers,
            'new_this_week' => $newUsers,
        ];
    }
}
