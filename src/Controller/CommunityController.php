<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommunityController extends AbstractController
{
    #[Route('/community', name: 'community_index')]
    public function index(Request $request): Response
    {
        $posts = SampleData::getCommunityPosts();
        $user = SampleData::getCurrentUser();
        $tab = $request->query->get('tab', 'hot');
<<<<<<< Updated upstream

        // Sort based on tab
=======
        $topic = $request->query->get('topic', null);
        $searchQuery = trim((string) $request->query->get('q', ''));

        $qb = $this->em->getRepository(Post::class)->createQueryBuilder('p')
            ->leftJoin('p.author', 'u')
            ->addSelect('u');

        // Search filter
        if ($searchQuery !== '') {
            $qb->leftJoin('p.tags', 't')
                ->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->like('p.title', ':search'),
                        $qb->expr()->like('p.content', ':search'),
                        $qb->expr()->like('t.name', ':search'),
                        $qb->expr()->like('u.username', ':search'),
                        $qb->expr()->like('u.firstName', ':search'),
                        $qb->expr()->like('u.lastName', ':search')
                    )
                )
                ->setParameter('search', '%' . $searchQuery . '%')
                ->groupBy('p.id');
        }

        // Topic filter
        if ($topic) {
            $qb->andWhere('p.topic = :topic')->setParameter('topic', $topic);
        }

        // Sorting
>>>>>>> Stashed changes
        switch ($tab) {
            case 'new':
                break;
            case 'top':
<<<<<<< Updated upstream
                usort($posts, fn($a, $b) => $b['upvotes'] <=> $a['upvotes']);
                break;
            case 'hot':
            default:
                break;
        }

=======
                $qb->leftJoin(Reaction::class, 'r', 'WITH', 'r.post = p.id')
                   ->addSelect('COUNT(r.id) as HIDDEN reactionCount')
                   ->groupBy('p.id')
                   ->orderBy('reactionCount', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;

            case 'unanswered':
                $qb->leftJoin('p.replies', 'rep')
                   ->addSelect('COUNT(rep.id) as HIDDEN replyCount')
                   ->groupBy('p.id')
                   ->having('replyCount = 0')
                   ->orderBy('p.createdAt', 'DESC');
                break;

            case 'hot':
            default:
                $qb->leftJoin(Reaction::class, 'r', 'WITH', 'r.post = p.id')
                   ->addSelect('COUNT(r.id) as HIDDEN reactionCount')
                   ->groupBy('p.id')
                   ->orderBy('reactionCount', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;
        }

        $posts = $qb->getQuery()->getResult();

        // Get current user for reactions
        $currentUser = $this->getUser();
        $reactionData = ($currentUser instanceof User) ? $this->getPostsReactionData($posts, $currentUser) : [];

        // Get topics for dropdown
        $topicsQ = $this->em->getRepository(Post::class)
            ->createQueryBuilder('p')
            ->select('DISTINCT p.topic as topic')
            ->where('p.topic IS NOT NULL')
            ->orderBy('p.topic', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $topics = array_values(array_filter(array_map(fn($r) => $r['topic'] ?? null, $topicsQ)));

>>>>>>> Stashed changes
        return $this->render('pages/community/index.html.twig', [
            'posts' => $posts,
            'user' => $user,
            'current_tab' => $tab,
<<<<<<< Updated upstream
=======
            'current_topic' => $topic,
            'topics' => $topics,
            'reactionData' => $reactionData,
            'searchQuery' => $searchQuery,
            'resultCount' => count($posts),
>>>>>>> Stashed changes
        ]);
    }

    #[Route('/community/create', name: 'community_create')]
    public function create(): Response
    {
        $user = SampleData::getCurrentUser();

        return $this->render('pages/community/create.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/community/{id}', name: 'community_post', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $posts = SampleData::getCommunityPosts();
        $user = SampleData::getCurrentUser();
        $post = array_values(array_filter($posts, fn($p) => $p['id'] === $id))[0] ?? null;

        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        return $this->render('pages/community/show.html.twig', [
            'post' => $post,
            'user' => $user,
        ]);
    }
}
