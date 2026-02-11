<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/admin")]
#[IsGranted("ROLE_ADMIN")]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ) {}

    #[Route("/", name: "admin_dashboard")]
    public function dashboard(): Response
    {
        // Get user statistics
        $stats = $this->userRepository->getUserStats();

        // Get recent users
        $recentUsers = $this->userRepository->findBy(
            [],
            ["createdAt" => "DESC"],
            10,
        );

        // Get all users for management
        $allUsers = $this->userRepository->findAllWithStats();

        return $this->render("pages/admin/dashboard.html.twig", [
            "stats" => $stats,
            "recent_users" => $recentUsers,
            "all_users" => $allUsers,
        ]);
    }

    #[Route("/users", name: "admin_users")]
    public function users(): Response
    {
        $users = $this->userRepository->findAllWithStats();

        return $this->render("pages/admin/users.html.twig", [
            "users" => $users,
        ]);
    }

    #[Route("/user/{id}/ban", name: "admin_user_ban", methods: ["POST"])]
    public function banUser(User $user): Response
    {
        if ($user->isAdmin()) {
            $this->addFlash("error", "Cannot ban an administrator.");
            return $this->redirectToRoute("admin_users");
        }

        $user->setIsBanned(true);
        $this->entityManager->flush();

        $this->addFlash(
            "success",
            sprintf("User %s has been banned successfully.", $user->getEmail()),
        );
        return $this->redirectToRoute("admin_users");
    }

    #[Route("/user/{id}/unban", name: "admin_user_unban", methods: ["POST"])]
    public function unbanUser(User $user): Response
    {
        $user->setIsBanned(false);
        $this->entityManager->flush();

        $this->addFlash(
            "success",
            sprintf(
                "User %s has been unbanned successfully.",
                $user->getEmail(),
            ),
        );
        return $this->redirectToRoute("admin_users");
    }

    #[Route("/user/{id}/delete", name: "admin_user_delete", methods: ["POST"])]
    public function deleteUser(User $user, Request $request): Response
    {
        if ($user->isAdmin()) {
            $this->addFlash("error", "Cannot delete an administrator.");
            return $this->redirectToRoute("admin_users");
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash(
            "success",
            sprintf(
                "User %s has been deleted successfully.",
                $user->getEmail(),
            ),
        );
        return $this->redirectToRoute("admin_users");
    }

    #[Route("/stats", name: "admin_stats")]
    public function stats(): Response
    {
        $stats = $this->userRepository->getUserStats();

        // Get user growth data (last 30 days)
        $userGrowth = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = new \DateTime("-$i days");
            $dateStart = clone $date;
            $dateStart->setTime(0, 0, 0);
            $dateEnd = clone $date;
            $dateEnd->setTime(23, 59, 59);

            $count = $this->userRepository
                ->createQueryBuilder("u")
                ->select("COUNT(u.id)")
                ->where("u.createdAt >= :dateStart")
                ->andWhere("u.createdAt <= :dateEnd")
                ->setParameter("dateStart", $dateStart)
                ->setParameter("dateEnd", $dateEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $userGrowth[] = [
                "date" => $date->format("M d"),
                "count" => $count,
            ];
        }

        return $this->render("pages/admin/stats.html.twig", [
            "stats" => $stats,
            "user_growth" => $userGrowth,
        ]);
    }
}
