<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository
    ) {}

    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        // Allow both ADMIN and PROFESSOR
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESSOR')) {
            throw new AccessDeniedException('Access denied. Admin or Professor access required.');
        }
        
        // Get user statistics
        $stats = $this->userRepository->getUserStats();
        
        // Get recent users
        $recentUsers = $this->userRepository->findBy([], ['createdAt' => 'DESC'], 10);
        
        // Get all users for management
        $allUsers = $this->userRepository->findAllWithStats();

        return $this->render('pages/admin/dashboard.html.twig', [
            'stats' => $stats,
            'recent_users' => $recentUsers,
            'all_users' => $allUsers,
        ]);
    }

    #[Route('/users', name: 'admin_users')]
    public function users(): Response
    {
        // Allow both ADMIN and PROFESSOR
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESSOR')) {
            throw new AccessDeniedException('Access denied. Admin or Professor access required.');
        }
        
        $users = $this->userRepository->findAllWithStats();
        
        return $this->render('pages/admin/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/user/{id}/ban', name: 'admin_user_ban', methods: ['POST'])]
    public function banUser(User $user): Response
    {
        if ($user->isAdmin()) {
            $this->addFlash('error', 'Cannot ban an administrator.');
            return $this->redirectToRoute('admin_users');
        }

        $user->setIsBanned(true);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('User %s has been banned successfully.', $user->getEmail()));
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/user/{id}/unban', name: 'admin_user_unban', methods: ['POST'])]
    public function unbanUser(User $user): Response
    {
        // Allow both ADMIN and PROFESSOR
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESSOR')) {
            throw new AccessDeniedException('Access denied. Admin or Professor access required.');
        }
        
        $user->setIsBanned(false);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('User %s has been unbanned successfully.', $user->getEmail()));
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/user/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user, Request $request): Response
    {
        // Allow ADMIN only for delete
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Access denied. Admin access required for user deletion.');
        }
        
        if ($user->isAdmin()) {
            $this->addFlash('error', 'Cannot delete an administrator.');
            return $this->redirectToRoute('admin_users');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('User %s has been deleted successfully.', $user->getEmail()));
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/user/{id}/promote/{role}', name: 'admin_user_promote', methods: ['POST'])]
    public function promoteUser(User $user, string $role): Response
    {
        // Allow both ADMIN and PROFESSOR
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESSOR')) {
            throw new AccessDeniedException('Access denied. Admin or Professor access required.');
        }
        
        // Validate role
        $validRoles = ['admin', 'professor', 'student'];
        if (!in_array($role, $validRoles)) {
            $this->addFlash('error', 'Invalid role specified.');
            return $this->redirectToRoute('admin_users');
        }

        // Update role in user_roles table
        $conn = $this->entityManager->getConnection();
        
        // Check if user already has a role
        $existingRole = $conn->fetchOne(
            'SELECT role FROM user_roles WHERE user_id = ?',
            [$user->getId()]
        );

        if ($existingRole) {
            // Update existing role
            $conn->executeStatement(
                'UPDATE user_roles SET role = ?, created_at = NOW() WHERE user_id = ?',
                [$role, $user->getId()]
            );
        } else {
            // Insert new role
            $conn->executeStatement(
                'INSERT INTO user_roles (user_id, role, created_at) VALUES (?, ?, NOW())',
                [$user->getId(), $role]
            );
        }

        $roleName = ucfirst($role);
        $this->addFlash('success', sprintf('User %s has been promoted to %s successfully.', $user->getEmail(), $roleName));
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/stats', name: 'admin_stats')]
    public function stats(): Response
    {
        // Allow both ADMIN and PROFESSOR
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESSOR')) {
            throw new AccessDeniedException('Access denied. Admin or Professor access required.');
        }
        
        $stats = $this->userRepository->getUserStats();
        
        // Get user growth data (last 30 days)
        $userGrowth = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = new \DateTime("-$i days");
            $dateStart = clone $date;
            $dateStart->setTime(0, 0, 0);
            $dateEnd = clone $date;
            $dateEnd->setTime(23, 59, 59);
            
            $count = $this->userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.createdAt >= :dateStart')
                ->andWhere('u.createdAt <= :dateEnd')
                ->setParameter('dateStart', $dateStart)
                ->setParameter('dateEnd', $dateEnd)
                ->getQuery()
                ->getSingleScalarResult();
            
            $userGrowth[] = [
                'date' => $date->format('M d'),
                'count' => $count
            ];
        }

        return $this->render('pages/admin/stats.html.twig', [
            'stats' => $stats,
            'user_growth' => $userGrowth,
        ]);
    }

    #[Route('/stats/export', name: 'admin_stats_export')]
    public function exportStats(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $stats = $this->userRepository->getUserStats();
        
        // Get user growth data (last 30 days)
        $userGrowth = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = new \DateTime("-$i days");
            $dateStart = clone $date;
            $dateStart->setTime(0, 0, 0);
            $dateEnd = clone $date;
            $dateEnd->setTime(23, 59, 59);
            
            $count = $this->userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.createdAt >= :dateStart')
                ->andWhere('u.createdAt <= :dateEnd')
                ->setParameter('dateStart', $dateStart)
                ->setParameter('dateEnd', $dateEnd)
                ->getQuery()
                ->getSingleScalarResult();
            
            $userGrowth[] = [
                'date' => $date->format('Y-m-d'),
                'formatted_date' => $date->format('M d, Y'),
                'count' => $count
            ];
        }

        // Get all users for export
        $allUsers = $this->userRepository->findAll();

        if ($format === 'csv') {
            return $this->exportCsv($stats, $userGrowth, $allUsers);
        } elseif ($format === 'pdf') {
            return $this->exportPdf($stats, $userGrowth, $allUsers);
        }

        return $this->redirectToRoute('admin_stats');
    }

    private function exportCsv(array $stats, array $userGrowth, array $users): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function() use ($stats, $userGrowth, $users) {
            $handle = fopen('php://output', 'w+');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Report Header
            fputcsv($handle, ['SkillHarbor Platform Statistics Report']);
            fputcsv($handle, ['Generated on: ' . date('F d, Y H:i:s')]);
            fputcsv($handle, []);

            // Overall Statistics
            fputcsv($handle, ['Overall Statistics']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total Users', $stats['total']]);
            fputcsv($handle, ['Active Users', $stats['active']]);
            fputcsv($handle, ['Banned Users', $stats['banned']]);
            fputcsv($handle, ['New This Week', $stats['new_this_week']]);
            fputcsv($handle, ['Activity Rate', number_format(($stats['active'] / $stats['total']) * 100, 2) . '%']);
            fputcsv($handle, []);

            // User Growth (Last 30 Days)
            fputcsv($handle, ['User Growth - Last 30 Days']);
            fputcsv($handle, ['Date', 'New Users']);
            foreach ($userGrowth as $day) {
                fputcsv($handle, [$day['formatted_date'], $day['count']]);
            }
            fputcsv($handle, []);

            // User List
            fputcsv($handle, ['Complete User List']);
            fputcsv($handle, ['ID', 'Email', 'First Name', 'Last Name', 'Role', 'Status', 'Joined Date']);
            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->getId(),
                    $user->getEmail(),
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getRole() ?? 'student',
                    $user->isBanned() ? 'Banned' : 'Active',
                    $user->getCreatedAt()->format('Y-m-d H:i:s')
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="skillharbor_stats_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    private function exportPdf(array $stats, array $userGrowth, array $users): Response
    {
        // Generate HTML for PDF
        $html = $this->renderView('pages/admin/stats_export.html.twig', [
            'stats' => $stats,
            'user_growth' => $userGrowth,
            'users' => $users,
            'generated_at' => new \DateTime()
        ]);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8'
        ]);
    }
}
