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

    // Validation constants
    private const TITLE_MIN_LENGTH = 5;
    private const TITLE_MAX_LENGTH = 255;
    private const CONTENT_MIN_LENGTH = 10;
    private const CONTENT_MAX_LENGTH = 10000;
    private const TOPIC_MAX_LENGTH = 100;
    private const TAG_MAX_LENGTH = 50;
    private const MAX_TAGS = 5;
    private const REPLY_MIN_LENGTH = 1;
    private const REPLY_MAX_LENGTH = 5000;
    private const DESCRIPTION_MAX_LENGTH = 1000;

    private const ALLOWED_POST_TYPES = ['question', 'discussion', 'article'];

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

        // Validate tab parameter
        $allowedTabs = ['hot', 'new', 'top', 'unanswered'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'hot';
        }

        // Sanitize search query
        if (strlen($searchQuery) > 200) {
            $searchQuery = substr($searchQuery, 0, 200);
        }

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
                $reactionCounts[$row['type']] = (int) $row['count'];
                $total += (int) $row['count'];
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
            // Get and trim input
            $type = trim((string) $request->request->get('type', 'question'));
            $title = trim((string) $request->request->get('title', ''));
            $topic = trim((string) $request->request->get('topic', ''));
            $content = trim((string) $request->request->get('content', ''));
            $tagsRaw = trim((string) $request->request->get('tags', ''));

            // Validation array to collect errors
            $errors = [];

            // 1. Validate Type
            if (!in_array($type, self::ALLOWED_POST_TYPES, true)) {
                $type = 'question'; // Default to safe value
            }

            // 2. Validate Title
            if ($title === '') {
                $errors[] = 'Title is required.';
            } elseif (strlen($title) < self::TITLE_MIN_LENGTH) {
                $errors[] = sprintf('Title must be at least %d characters long.', self::TITLE_MIN_LENGTH);
            } elseif (strlen($title) > self::TITLE_MAX_LENGTH) {
                $errors[] = sprintf('Title cannot exceed %d characters.', self::TITLE_MAX_LENGTH);
            }

            // 3. Validate Content
            if ($content === '') {
                $errors[] = 'Content is required.';
            } elseif (strlen($content) < self::CONTENT_MIN_LENGTH) {
                $errors[] = sprintf('Content must be at least %d characters long.', self::CONTENT_MIN_LENGTH);
            } elseif (strlen($content) > self::CONTENT_MAX_LENGTH) {
                $errors[] = sprintf('Content cannot exceed %d characters.', self::CONTENT_MAX_LENGTH);
            }

            // 4. Validate Topic (optional)
            if ($topic !== '' && strlen($topic) > self::TOPIC_MAX_LENGTH) {
                $errors[] = sprintf('Topic cannot exceed %d characters.', self::TOPIC_MAX_LENGTH);
            }

            // 5. Sanitize HTML (prevent XSS)
            $title = strip_tags($title); // Remove ALL HTML from title
            $content = strip_tags($content, '<p><br><strong><em><ul><ol><li><code><pre><blockquote>'); // Allow some safe tags in content
            $topic = strip_tags($topic);

            // 6. Validate Tags
            $tagNames = array_filter(array_unique(array_map('trim', preg_split('/[,]+/', $tagsRaw))));
            if (count($tagNames) > self::MAX_TAGS) {
                $errors[] = sprintf('Maximum %d tags allowed.', self::MAX_TAGS);
            }

            foreach ($tagNames as $tagName) {
                if (strlen($tagName) > self::TAG_MAX_LENGTH) {
                    $errors[] = sprintf('Tag "%s" is too long (max %d characters).', $tagName, self::TAG_MAX_LENGTH);
                    break;
                }
            }

            // 7. Check for spam patterns (basic)
            if ($this->containsSpam($title) || $this->containsSpam($content)) {
                $errors[] = 'Your post contains spam-like content. Please revise and try again.';
            }

            // Show all errors
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error);
                }
            } else {
                // All validations passed - create post
                try {
                    $post = new Post();
                    $post->setType($type);
                    $post->setTitle($title);
                    $post->setTopic($topic === '' ? null : $topic);
                    $post->setContent($content);
                    $post->setAuthor($user);

                    // Process tags
                    foreach ($tagNames as $tagName) {
                        if ($tagName === '')
                            continue;

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
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Failed to create post. Please try again.');
                }
            }
        }

        return $this->render('pages/community/create.html.twig');
    }

    #[Route('/community/upload-image', name: 'community_upload_image', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadImage(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $file = $request->files->get('image');

        if (!$file) {
            return $this->json(['ok' => false, 'error' => 'No file uploaded'], 400);
        }

        // Validate file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return $this->json(['ok' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.'], 400);
        }

        // Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['ok' => false, 'error' => 'File size exceeds 5MB limit'], 400);
        }

        try {
            // Generate unique filename
            $extension = $file->guessExtension();
            $filename = uniqid('post_img_', true) . '.' . $extension;

            // Move file to public/uploads/community directory
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/community';

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file->move($uploadDir, $filename);

            // Return URL path (not full URL, just the path from /uploads/)
            $url = '/uploads/community/' . $filename;

            return $this->json([
                'ok' => true,
                'url' => $url,
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => 'Failed to upload image: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/community/{id}', name: 'community_post', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        // Validate ID
        if ($id < 1) {
            throw $this->createNotFoundException('Invalid post ID');
        }

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
            $counts[$row['type']] = (int) $row['count'];
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

            $errors = [];

            // Validate Title
            if ($title === '') {
                $errors[] = 'Title is required.';
            } elseif (strlen($title) < self::TITLE_MIN_LENGTH) {
                $errors[] = sprintf('Title must be at least %d characters long.', self::TITLE_MIN_LENGTH);
            } elseif (strlen($title) > self::TITLE_MAX_LENGTH) {
                $errors[] = sprintf('Title cannot exceed %d characters.', self::TITLE_MAX_LENGTH);
            }

            // Validate Content
            if ($content === '') {
                $errors[] = 'Content is required.';
            } elseif (strlen($content) < self::CONTENT_MIN_LENGTH) {
                $errors[] = sprintf('Content must be at least %d characters long.', self::CONTENT_MIN_LENGTH);
            } elseif (strlen($content) > self::CONTENT_MAX_LENGTH) {
                $errors[] = sprintf('Content cannot exceed %d characters.', self::CONTENT_MAX_LENGTH);
            }

            // Validate Topic
            if ($topic !== '' && strlen($topic) > self::TOPIC_MAX_LENGTH) {
                $errors[] = sprintf('Topic cannot exceed %d characters.', self::TOPIC_MAX_LENGTH);
            }

            // Sanitize
            $title = strip_tags($title);
            $content = strip_tags($content, '<p><br><strong><em><ul><ol><li><code><pre><blockquote>');
            $topic = strip_tags($topic);

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error);
                }
            } else {
                try {
                    $post->setTitle($title);
                    $post->setContent($content);
                    $post->setTopic($topic === '' ? null : $topic);

                    $this->em->flush();

                    $this->addFlash('success', 'Post updated successfully.');
                    return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Failed to update post. Please try again.');
                }
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

        try {
            $this->em->remove($post);
            $this->em->flush();

            $this->addFlash('success', 'Post deleted successfully.');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Failed to delete post. Please try again.');
            return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
        }

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

        $errors = [];

        // Validate Reply Content
        if ($content === '') {
            $errors[] = 'Reply cannot be empty.';
        } elseif (strlen($content) < self::REPLY_MIN_LENGTH) {
            $errors[] = sprintf('Reply must be at least %d character long.', self::REPLY_MIN_LENGTH);
        } elseif (strlen($content) > self::REPLY_MAX_LENGTH) {
            $errors[] = sprintf('Reply cannot exceed %d characters.', self::REPLY_MAX_LENGTH);
        }

        // Sanitize HTML
        $content = strip_tags($content, '<p><br><strong><em><ul><ol><li><code><pre><blockquote>');

        // Check for spam
        if ($this->containsSpam($content)) {
            $errors[] = 'Your reply contains spam-like content. Please revise and try again.';
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error);
            }
            return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
        }

        try {
            $reply = new Reply();
            $reply->setPost($post);
            $reply->setContent($content);
            $reply->setAuthor($user);

            $this->em->persist($reply);
            $this->em->flush();

            $this->addFlash('success', 'Your reply was posted successfully.');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Failed to post reply. Please try again.');
        }

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

        // Validate
        if ($content === '') {
            return $this->json(['ok' => false, 'error' => 'Content cannot be empty'], 400);
        }

        if (strlen($content) < self::REPLY_MIN_LENGTH) {
            return $this->json(['ok' => false, 'error' => sprintf('Reply must be at least %d character.', self::REPLY_MIN_LENGTH)], 400);
        }

        if (strlen($content) > self::REPLY_MAX_LENGTH) {
            return $this->json(['ok' => false, 'error' => sprintf('Reply cannot exceed %d characters.', self::REPLY_MAX_LENGTH)], 400);
        }

        // Sanitize
        $content = strip_tags($content, '<p><br><strong><em><ul><ol><li><code><pre><blockquote>');

        try {
            $reply->setContent($content);
            $reply->setUpdatedAt(new \DateTime());
            $this->em->flush();

            return $this->json([
                'ok' => true,
                'content' => $content,
                'updatedAt' => $reply->getUpdatedAt()->format('M j, Y, H:i')
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => 'Failed to update reply'], 500);
        }
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

        try {
            $this->em->remove($reply);
            $this->em->flush();

            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => 'Failed to delete reply'], 500);
        }
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

        // Validate reaction type
        if (!in_array($reactionType, Reaction::AVAILABLE_TYPES, true)) {
            return $this->json(['ok' => false, 'error' => 'Invalid reaction type'], 400);
        }

        try {
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
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => 'Failed to process reaction'], 500);
        }
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

        // Validate reaction type
        if (!in_array($reactionType, Reaction::AVAILABLE_TYPES, true)) {
            return $this->json(['ok' => false, 'error' => 'Invalid reaction type'], 400);
        }

        try {
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
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => 'Failed to process reaction'], 500);
        }
    }

    #[Route('/community/{id}/report', name: 'community_report_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportPost(Request $request, Post $post): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $reason = trim((string) $request->request->get('reason', ''));
        $description = trim((string) $request->request->get('description', ''));

        // Validate reason
        if (!array_key_exists($reason, Report::AVAILABLE_REASONS)) {
            return $this->json(['ok' => false, 'error' => 'Invalid report reason'], 400);
        }

        // Validate description length
        if (strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            return $this->json(['ok' => false, 'error' => sprintf('Description cannot exceed %d characters', self::DESCRIPTION_MAX_LENGTH)], 400);
        }

        // Sanitize description
        $description = strip_tags($description);

        // Check if user already reported this post
        $existingReport = $this->em->getRepository(Report::class)->findOneBy([
            'post' => $post,
            'reporter' => $user
        ]);

        if ($existingReport) {
            return $this->json(['ok' => false, 'error' => 'You have already reported this post'], 400);
        }

        try {
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
                'message' => 'Report submitted successfully. Our moderation team will review it soon.'
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => 'Failed to submit report. Please try again.'], 500);
        }
    }

    /**
     * Simple spam detection helper
     */
    private function containsSpam(string $text): bool
    {
        // Convert to lowercase for case-insensitive matching
        $lowerText = strtolower($text);

        // Common spam patterns
        $spamPatterns = [
            '/\b(buy now|click here|limited time|act now)\b/i',
            '/\b(viagra|cialis|pharmacy|casino|lottery)\b/i',
            '/\b(earn \$\d+|make money fast|work from home)\b/i',
            '/(http|https):\/\/.*\.(ru|tk|ml|ga|cf)/', // Suspicious domains
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $lowerText)) {
                return true;
            }
        }

        // Check for excessive URLs (more than 3)
        if (substr_count($lowerText, 'http') > 3) {
            return true;
        }

        // Check for excessive capital letters (more than 50%)
        $capitals = preg_match_all('/[A-Z]/', $text);
        $letters = preg_match_all('/[a-zA-Z]/', $text);
        if ($letters > 0 && ($capitals / $letters) > 0.5 && strlen($text) > 20) {
            return true;
        }

        return false;
    }

    #[Route('/community/reply/{id}/reply', name: 'community_reply_to_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function replyToReply(Request $request, Reply $parentReply): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $content = trim((string) $request->request->get('content', ''));

        // Validation
        if ($content === '') {
            return $this->json(['ok' => false, 'error' => 'Reply cannot be empty'], 400);
        }

        if (strlen($content) < self::REPLY_MIN_LENGTH) {
            return $this->json(['ok' => false, 'error' => sprintf('Reply must be at least %d character.', self::REPLY_MIN_LENGTH)], 400);
        }

        if (strlen($content) > self::REPLY_MAX_LENGTH) {
            return $this->json(['ok' => false, 'error' => sprintf('Reply cannot exceed %d characters.', self::REPLY_MAX_LENGTH)], 400);
        }

        // Sanitize
        $content = strip_tags($content, '<p><br><strong><em><ul><ol><li><code><pre><blockquote>');

        // Check for spam
        if ($this->containsSpam($content)) {
            return $this->json(['ok' => false, 'error' => 'Your reply contains spam-like content.'], 400);
        }

        // Limit nesting depth (optional - prevent infinite nesting)
        $maxDepth = 5;
        if ($parentReply->getDepth() >= $maxDepth) {
            return $this->json(['ok' => false, 'error' => 'Maximum reply depth reached'], 400);
        }

        try {
            $reply = new Reply();
            $reply->setPost($parentReply->getPost());
            $reply->setParent($parentReply);
            $reply->setContent($content);
            $reply->setAuthor($user);

            $this->em->persist($reply);
            $this->em->flush();

            return $this->json([
                'ok' => true,
                'reply' => [
                    'id' => $reply->getId(),
                    'content' => $content,
                    'author' => [
                        'fullName' => $user->getFullName(),
                        'avatar' => $user->getAvatar()
                    ],
                    'createdAt' => $reply->getCreatedAt()->format('M j, Y, H:i'),
                    'depth' => $reply->getDepth()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => 'Failed to post reply'], 500);
        }
    }
}