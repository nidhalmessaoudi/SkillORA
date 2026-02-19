<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Reply;
use App\Entity\Tag;
use App\Entity\Reaction;
use App\Entity\User;
use App\Entity\Report;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CommunityController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/community', name: 'community_index')]
    public function index(Request $request): Response
    {
        $tab = $request->query->get('tab', 'hot');
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
        switch ($tab) {
            case 'new':
                $qb->orderBy('p.createdAt', 'DESC');
                break;

            case 'top':
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

        return $this->render('pages/community/index.html.twig', [
            'posts' => $posts,
            'current_tab' => $tab,
            'current_topic' => $topic,
            'topics' => $topics,
            'reactionData' => $reactionData,
            'searchQuery' => $searchQuery,
            'resultCount' => count($posts),
        ]);
    }

    private function getPostsReactionData(array $posts, User $user): array
    {
        $data = [];
        $reactionRepo = $this->em->getRepository(Reaction::class);

        foreach ($posts as $post) {
            $userReaction = $reactionRepo->findOneBy([
                'user' => $user,
                'post' => $post
            ]);

            $qb = $reactionRepo->createQueryBuilder('r')
                ->select('r.type, COUNT(r.id) as count')
                ->where('r.post = :post')
                ->setParameter('post', $post)
                ->groupBy('r.type');

            $counts = $qb->getQuery()->getResult();
            
            $reactionCounts = [];
            $total = 0;
            foreach ($counts as $row) {
                $reactionCounts[$row['type']] = (int)$row['count'];
                $total += (int)$row['count'];
            }

            $data[$post->getId()] = [
                'userReaction' => $userReaction ? $userReaction->getType() : null,
                'counts' => $reactionCounts,
                'total' => $total
            ];
        }

        return $data;
    }

#[Route('/community/create', name: 'community_create', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_USER')]
public function create(Request $request): Response
{
    $user = $this->getUser();
    if (!$user instanceof User) {
        throw $this->createAccessDeniedException('You must be logged in to create a post.');
    }

    if ($request->isMethod('POST')) {
        $type = $request->request->get('type', 'question');
        $title = trim((string) $request->request->get('title', ''));
        $topic = trim((string) $request->request->get('topic', ''));
        $content = trim((string) $request->request->get('content', ''));
        $tagsRaw = trim((string) $request->request->get('tags', ''));

        // Validation errors array
        $errors = [];

        // Validate type
        if (!in_array($type, ['question', 'discussion', 'article'])) {
            $errors['type'] = 'Invalid post type selected.';
        }

        // Validate title
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) < 10) {
            $errors['title'] = 'Title must be at least 10 characters long.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Title must not exceed 255 characters.';
        }

        // Validate content
        if ($content === '') {
            $errors['content'] = 'Content is required.';
        } elseif (mb_strlen($content) < 30) {
            $errors['content'] = 'Content must be at least 30 characters long.';
        }

        // Validate tags
        $tagNames = [];
        if ($tagsRaw !== '') {
            $tagNames = array_filter(array_unique(array_map('trim', preg_split('/[,]+/', $tagsRaw))));
            
            if (count($tagNames) > 5) {
                $errors['tags'] = 'Maximum 5 tags allowed.';
            }

            foreach ($tagNames as $tagName) {
                if (mb_strlen($tagName) > 50) {
                    $errors['tags'] = 'Each tag must not exceed 50 characters.';
                    break;
                }
            }
        }

        // If there are validation errors, show them and return
        if (!empty($errors)) {
            foreach ($errors as $field => $error) {
                $this->addFlash('danger', $error);
            }

            return $this->render('pages/community/create.html.twig', [
                'errors' => $errors,
                'form_data' => [
                    'type' => $type,
                    'title' => $title,
                    'topic' => $topic,
                    'content' => $content,
                    'tags' => $tagsRaw,
                ]
            ]);
        }

        // If validation passed, create the post
        $post = new Post();
        $post->setType($type);
        $post->setTitle($title);
        $post->setTopic($topic === '' ? null : $topic);
        $post->setContent($content);
        $post->setAuthor($user);

        // Add tags
        foreach ($tagNames as $tagName) {
            if ($tagName === '') continue;
            
            $existing = $this->em->getRepository(Tag::class)->findOneBy(['name' => $tagName]);
            if ($existing) {
                $post->addTag($existing);
            } else {
                $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($tagName)));
                $tag = new Tag();
                $tag->setName($tagName);
                $tag->setSlug($slug);
                $this->em->persist($tag);
                $post->addTag($tag);
            }
        }

        $this->em->persist($post);
        $this->em->flush();

        $this->addFlash('success', 'Post published successfully!');
        return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
    }

    return $this->render('pages/community/create.html.twig');
}

    #[Route('/community/{id}', name: 'community_post', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $post = $this->em->getRepository(Post::class)->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        $currentUser = $this->getUser();
        $reactionRepo = $this->em->getRepository(Reaction::class);

        // Get user's reaction on the post
        $userPostReaction = null;
        if ($currentUser instanceof User) {
            $postReaction = $reactionRepo->findOneBy(['user' => $currentUser, 'post' => $post]);
            $userPostReaction = $postReaction ? $postReaction->getType() : null;
        }

        // Get post reaction counts
        $postReactionCounts = $this->getReactionCounts($post, null);

        // Get reply reaction data
        $replyReactionData = [];
        foreach ($post->getReplies() as $reply) {
            $userReaction = null;
            if ($currentUser instanceof User) {
                $replyReaction = $reactionRepo->findOneBy(['user' => $currentUser, 'reply' => $reply]);
                $userReaction = $replyReaction ? $replyReaction->getType() : null;
            }
            
            $replyReactionData[$reply->getId()] = [
                'userReaction' => $userReaction,
                'counts' => $this->getReactionCounts(null, $reply),
            ];
        }

        return $this->render('pages/community/show.html.twig', [
            'post' => $post,
            'userPostReaction' => $userPostReaction,
            'postReactionCounts' => $postReactionCounts,
            'replyReactionData' => $replyReactionData,
        ]);
    }

    private function getReactionCounts(?Post $post, ?Reply $reply): array
    {
        $qb = $this->em->getRepository(Reaction::class)->createQueryBuilder('r')
            ->select('r.type, COUNT(r.id) as count')
            ->groupBy('r.type');

        if ($post) {
            $qb->where('r.post = :post')->setParameter('post', $post);
        } elseif ($reply) {
            $qb->where('r.reply = :reply')->setParameter('reply', $reply);
        }

        $results = $qb->getQuery()->getResult();
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['type']] = (int)$row['count'];
        }

        return $counts;
    }

#[Route('/community/{id}/edit', name: 'community_edit_post', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_USER')]
public function editPost(Request $request, Post $post): Response
{
    $user = $this->getUser();
    if (!$user instanceof User) {
        throw $this->createAccessDeniedException();
    }

    // Check if user owns this post
    if ($post->getAuthor()->getId() !== $user->getId()) {
        $this->addFlash('danger', 'You can only edit your own posts.');
        return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
    }

    if ($request->isMethod('POST')) {
        $title = trim((string) $request->request->get('title', ''));
        $content = trim((string) $request->request->get('content', ''));
        $topic = trim((string) $request->request->get('topic', ''));

        // Validation
        $errors = [];

        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) < 10) {
            $errors['title'] = 'Title must be at least 10 characters long.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Title must not exceed 255 characters.';
        }

        if ($content === '') {
            $errors['content'] = 'Content is required.';
        } elseif (mb_strlen($content) < 30) {
            $errors['content'] = 'Content must be at least 30 characters long.';
        }

        if (!empty($errors)) {
            foreach ($errors as $field => $error) {
                $this->addFlash('danger', $error);
            }

            return $this->render('pages/community/edit.html.twig', [
                'post' => $post,
                'errors' => $errors,
            ]);
        }

        // Update post
        $post->setTitle($title);
        $post->setContent($content);
        $post->setTopic($topic === '' ? null : $topic);

        $this->em->flush();

        $this->addFlash('success', 'Post updated successfully.');
        return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
    }

    return $this->render('pages/community/edit.html.twig', [
        'post' => $post,
    ]);
}

    #[Route('/community/{id}/delete', name: 'community_delete_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deletePost(Post $post): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Check if user owns this post or is admin
        if ($post->getAuthor()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'You can only delete your own posts.');
            return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
        }

        $this->em->remove($post);
        $this->em->flush();

        $this->addFlash('success', 'Post deleted successfully.');
        return $this->redirectToRoute('community_index');
    }

    #[Route('/community/{id}/reply', name: 'community_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reply(Request $request, Post $post): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $content = trim((string) $request->request->get('content', ''));
        if ($content === '') {
            $this->addFlash('danger', 'Reply content cannot be empty.');
            return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
        }

        $reply = new Reply();
        $reply->setPost($post);
        $reply->setContent($content);
        $reply->setAuthor($user);

        $this->em->persist($reply);
        $this->em->flush();

        $this->addFlash('success', 'Your reply was posted.');

        return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
    }

    #[Route('/community/reply/{id}/edit', name: 'community_edit_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function editReply(Request $request, Reply $reply): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        // Check if user owns this reply
        if ($reply->getAuthor()->getId() !== $user->getId()) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $content = trim((string) $request->request->get('content', ''));
        if ($content === '') {
            return $this->json(['ok' => false, 'error' => 'Content cannot be empty'], 400);
        }

        $reply->setContent($content);
        $reply->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'content' => $content,
            'updatedAt' => $reply->getUpdatedAt()->format('M j, Y, H:i')
        ]);
    }

    #[Route('/community/reply/{id}/delete', name: 'community_delete_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteReply(Reply $reply): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        // Check if user owns this reply or is admin
        if ($reply->getAuthor()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $this->em->remove($reply);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/community/{id}/react', name: 'community_react_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reactToPost(Request $request, Post $post): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $reactionType = $request->request->get('type', 'like');

        if (!in_array($reactionType, Reaction::AVAILABLE_TYPES, true)) {
            return $this->json(['ok' => false, 'error' => 'Invalid reaction type'], 400);
        }

        $reactionRepo = $this->em->getRepository(Reaction::class);
        $existing = $reactionRepo->findOneBy(['user' => $user, 'post' => $post]);

        if ($existing) {
            if ($existing->getType() === $reactionType) {
                $this->em->remove($existing);
                $userReaction = null;
            } else {
                $existing->setType($reactionType);
                $this->em->persist($existing);
                $userReaction = $reactionType;
            }
        } else {
            $reaction = new Reaction();
            $reaction->setUser($user)
                ->setType($reactionType)
                ->setPost($post)
                ->setReply(null);
            $this->em->persist($reaction);
            $userReaction = $reactionType;
        }

        $this->em->flush();

        $counts = $this->getReactionCounts($post, null);
        $total = array_sum($counts);

        return $this->json([
            'ok' => true,
            'userReaction' => $userReaction,
            'counts' => $counts,
            'total' => $total
        ]);
    }

    #[Route('/community/reply/{id}/react', name: 'community_react_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reactToReply(Request $request, Reply $reply): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $reactionType = $request->request->get('type', 'like');

        if (!in_array($reactionType, Reaction::AVAILABLE_TYPES, true)) {
            return $this->json(['ok' => false, 'error' => 'Invalid reaction type'], 400);
        }

        $reactionRepo = $this->em->getRepository(Reaction::class);
        $existing = $reactionRepo->findOneBy(['user' => $user, 'reply' => $reply]);

        if ($existing) {
            if ($existing->getType() === $reactionType) {
                $this->em->remove($existing);
                $userReaction = null;
            } else {
                $existing->setType($reactionType);
                $this->em->persist($existing);
                $userReaction = $reactionType;
            }
        } else {
            $reaction = new Reaction();
            $reaction->setUser($user)
                ->setType($reactionType)
                ->setReply($reply)
                ->setPost(null);
            $this->em->persist($reaction);
            $userReaction = $reactionType;
        }

        $this->em->flush();

        $counts = $this->getReactionCounts(null, $reply);
        $total = array_sum($counts);

        return $this->json([
            'ok' => true,
            'userReaction' => $userReaction,
            'counts' => $counts,
            'total' => $total
        ]);
    }

    #[Route('/community/{id}/report', name: 'community_report_post', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function reportPost(Request $request, Post $post): JsonResponse
{
    $user = $this->getUser();
    if (!$user instanceof User) {
        return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
    }

    $reason = $request->request->get('reason', '');
    $description = trim((string) $request->request->get('description', ''));

    // Validate reason
    if (!array_key_exists($reason, Report::AVAILABLE_REASONS)) {
        return $this->json(['ok' => false, 'error' => 'Invalid report reason'], 400);
    }

    // Check if user already reported this post
    $existingReport = $this->em->getRepository(Report::class)->findOneBy([
        'post' => $post,
        'reporter' => $user
    ]);

    if ($existingReport) {
        return $this->json(['ok' => false, 'error' => 'You have already reported this post'], 400);
    }

    // Create report
    $report = new Report();
    $report->setPost($post);
    $report->setReporter($user);
    $report->setReason($reason);
    $report->setDescription($description);

    $this->em->persist($report);
    $this->em->flush();

    return $this->json([
        'ok' => true,
        'message' => 'Report submitted successfully. Our team will review it soon.'
    ]);
}
}