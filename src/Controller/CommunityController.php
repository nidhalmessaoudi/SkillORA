<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Reply;
use App\Entity\Tag;
use App\Entity\Reaction;
use App\Entity\User;
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

        $qb = $this->em->getRepository(Post::class)->createQueryBuilder('p');

        if ($topic) {
            $qb->andWhere('p.topic = :topic')->setParameter('topic', $topic);
        }

        switch ($tab) {
            case 'new':
                $qb->orderBy('p.createdAt', 'DESC');
                break;

            case 'top':
                $qb->leftJoin(Reaction::class, 'r', 'WITH', 'r.post = p.id')
                   ->groupBy('p.id')
                   ->orderBy('COUNT(r.id)', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;

            case 'unanswered':
                $qb->leftJoin('p.replies', 'rep')
                   ->groupBy('p.id')
                   ->having('COUNT(rep.id) = 0')
                   ->orderBy('p.createdAt', 'DESC');
                break;

            case 'hot':
            default:
                $qb->leftJoin(Reaction::class, 'r', 'WITH', 'r.post = p.id')
                   ->groupBy('p.id')
                   ->orderBy('COUNT(r.id)', 'DESC')
                   ->addOrderBy('p.createdAt', 'DESC');
                break;
        }

        $posts = $qb->getQuery()->getResult();

        // Get current user for reactions
        $currentUser = $this->getUser();
        $reactionData = ($currentUser instanceof User) ? $this->getPostsReactionData($posts, $currentUser) : [];

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

            if ($title === '' || $content === '') {
                $this->addFlash('danger', 'Title and content are required.');
            } else {
                $post = new Post();
                $post->setType($type);
                $post->setTitle($title);
                $post->setTopic($topic === '' ? null : $topic);
                $post->setContent($content);
                $post->setAuthor($user);

                $tagNames = array_filter(array_unique(array_map('trim', preg_split('/[,]+/', $tagsRaw))));
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

                $this->addFlash('success', 'Post published.');
                return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
            }
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

            if ($title === '' || $content === '') {
                $this->addFlash('danger', 'Title and content are required.');
            } else {
                $post->setTitle($title);
                $post->setContent($content);
                $post->setTopic($topic === '' ? null : $topic);

                $this->em->flush();

                $this->addFlash('success', 'Post updated successfully.');
                return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
            }
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
}