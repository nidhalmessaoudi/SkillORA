<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Report;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/posts')]
#[IsGranted('ROLE_ADMIN')]
class AdminPostsController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('', name: 'admin_posts_index')]
    public function index(Request $request): Response
    {
        // Validate and sanitize page number
        $page = $request->query->getInt('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Get total count
        $totalPosts = $this->em->getRepository(Post::class)->count([]);

        // Validate page doesn't exceed maximum
        $totalPages = max(1, ceil($totalPosts / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit;
        }

        // Get posts with pagination
        $posts = $this->em->getRepository(Post::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.author', 'u')
            ->addSelect('u')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        // Get report counts for each post
        $reportCounts = [];
        foreach ($posts as $post) {
            $count = $this->em->getRepository(Report::class)->count([
                'post' => $post,
                'status' => Report::STATUS_PENDING
            ]);
            $reportCounts[$post->getId()] = $count;
        }

        return $this->render('pages/admin/posts/index.html.twig', [
            'posts' => $posts,
            'reportCounts' => $reportCounts,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalPosts' => $totalPosts,
        ]);
    }

    #[Route('/{id}', name: 'admin_posts_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        // Validate ID is positive
        if ($id < 1) {
            throw $this->createNotFoundException('Invalid post ID');
        }

        $post = $this->em->getRepository(Post::class)->find($id);
        
        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        // Get all reports for this post
        $reports = $this->em->getRepository(Report::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'u')
            ->addSelect('u')
            ->where('r.post = :post')
            ->setParameter('post', $post)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('pages/admin/posts/show.html.twig', [
            'post' => $post,
            'reports' => $reports,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_posts_delete', methods: ['POST'])]
    public function delete(int $id): JsonResponse
    {
        // Validate ID
        if ($id < 1) {
            return $this->json([
                'ok' => false,
                'error' => 'Invalid post ID'
            ], 400);
        }

        $post = $this->em->getRepository(Post::class)->find($id);
        
        if (!$post) {
            return $this->json([
                'ok' => false,
                'error' => 'Post not found'
            ], 404);
        }

        try {
            $this->em->remove($post);
            $this->em->flush();

            return $this->json([
                'ok' => true,
                'message' => 'Post deleted successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Failed to delete post: Database error'
            ], 500);
        }
    }

    #[Route('/reports', name: 'admin_reports_index')]
    public function reports(Request $request): Response
    {
        // Validate and sanitize status parameter
        $status = $request->query->get('status', 'pending');
        $allowedStatuses = ['pending', 'reviewed', 'dismissed', 'all'];
        
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        // Build query
        $qb = $this->em->getRepository(Report::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.post', 'p')
            ->leftJoin('r.reporter', 'u')
            ->leftJoin('p.author', 'a')
            ->addSelect('p', 'u', 'a')
            ->orderBy('r.createdAt', 'DESC');

        if ($status !== 'all') {
            $qb->where('r.status = :status')
               ->setParameter('status', $status);
        }

        $reports = $qb->getQuery()->getResult();

        // Get counts by status
        $statusCounts = [
            'pending' => $this->em->getRepository(Report::class)->count(['status' => Report::STATUS_PENDING]),
            'reviewed' => $this->em->getRepository(Report::class)->count(['status' => Report::STATUS_REVIEWED]),
            'dismissed' => $this->em->getRepository(Report::class)->count(['status' => Report::STATUS_DISMISSED]),
            'all' => $this->em->getRepository(Report::class)->count([]),
        ];

        return $this->render('pages/admin/posts/reports.html.twig', [
            'reports' => $reports,
            'currentStatus' => $status,
            'statusCounts' => $statusCounts,
        ]);
    }

    #[Route('/reports/{id}/resolve', name: 'admin_reports_resolve', methods: ['POST'])]
    public function resolveReport(Request $request, int $id): JsonResponse
    {
        // Validate ID
        if ($id < 1) {
            return $this->json([
                'ok' => false,
                'error' => 'Invalid report ID'
            ], 400);
        }

        $report = $this->em->getRepository(Report::class)->find($id);
        
        if (!$report) {
            return $this->json([
                'ok' => false,
                'error' => 'Report not found'
            ], 404);
        }

        // Validate action parameter
        $action = $request->request->get('action');
        $allowedActions = ['dismiss', 'delete_post'];
        
        if (!in_array($action, $allowedActions, true)) {
            return $this->json([
                'ok' => false,
                'error' => 'Invalid action specified'
            ], 400);
        }

        // Get current admin user
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'ok' => false,
                'error' => 'Unauthorized'
            ], 401);
        }

        try {
            $report->setReviewedAt(new \DateTime());
            $report->setReviewedBy($user);

            if ($action === 'delete_post') {
                // Delete the post (this will cascade delete the report)
                $this->em->remove($report->getPost());
                $report->setStatus(Report::STATUS_RESOLVED);
                $message = 'Post deleted successfully';
            } else {
                // Just dismiss the report
                $report->setStatus(Report::STATUS_DISMISSED);
                $message = 'Report dismissed';
            }

            $this->em->flush();

            return $this->json([
                'ok' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Failed to resolve report: Database error'
            ], 500);
        }
    }
}