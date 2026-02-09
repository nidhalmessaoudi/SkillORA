<?php
namespace App\Controller;

use App\Entity\Post;
use App\Entity\Reply;
use App\Entity\Tag;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommunityController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Returns a static user array used only for UI/testing.
     */
    private function getStaticUser(): array
    {
        return [
            'id' => 0,
            'username' => 'tester',
            'displayName' => 'Test User',
            'avatar' => '/images/avatar-placeholder.png',
            'xp' => 1234,
        ];
    }

    // inside App\Controller\CommunityController

#[Route('/community', name: 'community_index')]
public function index(Request $request): Response
{
    $tab = $request->query->get('tab', 'hot');       // hot | new | top | unanswered
    $topic = $request->query->get('topic', null);    // topic slug or topic name

    $qb = $this->em->getRepository(Post::class)->createQueryBuilder('p');

    // optional topic filter
    if ($topic) {
        // if you store topic as string field:
        $qb->andWhere('p.topic = :topic')->setParameter('topic', $topic);
    }

    // apply tab sorting / filter
    switch ($tab) {
        case 'new':
            $qb->orderBy('p.createdAt', 'DESC');
            break;

        case 'top':
            // top by upvotes field
            $qb->orderBy('p.upvotes', 'DESC');
            $qb->addOrderBy('p.createdAt', 'DESC');
            break;

        case 'unanswered':
            // left join replies and return posts with zero replies
            $qb->leftJoin('p.replies', 'r')
               ->groupBy('p.id')
               ->having('COUNT(r.id) = 0')
               ->orderBy('p.createdAt', 'DESC');
            break;

        case 'hot':
        default:
            // "hot" heuristic: prefer upvotes then recent posts
            $qb->orderBy('p.upvotes', 'DESC');
            $qb->addOrderBy('p.createdAt', 'DESC');
            break;
    }

    // pagination: optionally add ->setMaxResults(...) / offset if you want
    $posts = $qb->getQuery()->getResult();

    // gather available topics for the dropdown (distinct non-null topic strings)
    $topicsQ = $this->em->getRepository(Post::class)
        ->createQueryBuilder('p')
        ->select('DISTINCT p.topic as topic')
        ->where('p.topic IS NOT NULL')
        ->orderBy('p.topic', 'ASC')
        ->getQuery()
        ->getArrayResult();

    // flatten the array of arrays into simple list of strings
    $topics = array_values(array_filter(array_map(fn($r) => $r['topic'] ?? null, $topicsQ)));

    return $this->render('pages/community/index.html.twig', [
        'posts' => $posts,
        'user' => $this->getStaticUser(),
        'current_tab' => $tab,
        'current_topic' => $topic,
        'topics' => $topics,
    ]);
}


    #[Route('/community/create', name: 'community_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
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

                // handle tags: comma separated
                $tagNames = array_filter(array_unique(array_map('trim', preg_split('/[,]+/', $tagsRaw))));
                foreach ($tagNames as $tagName) {
                    if ($tagName === '') {
                        continue;
                    }
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

        return $this->render('pages/community/create.html.twig', [
            'user' => $this->getStaticUser(),
        ]);
    }

    #[Route('/community/{id}', name: 'community_post', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $post = $this->em->getRepository(Post::class)->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        // static user identifier for testing
        $user = $this->getStaticUser();
        $uid = $user['username'] ?? ('guest_'.session_id());

        $voteRepo = $this->em->getRepository(Vote::class);

        // current user's vote on the post
        $postVote = $voteRepo->findOneBy(['userIdentifier' => $uid, 'post' => $post]);
        $userPostVote = $postVote ? $postVote->getValue() : 0;

        // compute per-reply user votes and totals
        $replyUserVotes = [];
        $replyCounts = []; // [id => ['up' => int, 'down' => int]]
        foreach ($post->getReplies() as $reply) {
            $rv = $voteRepo->findOneBy(['userIdentifier' => $uid, 'reply' => $reply]);
            $replyUserVotes[$reply->getId()] = $rv ? $rv->getValue() : 0;

            $up = (int) $voteRepo->createQueryBuilder('v')
                ->select('COUNT(v.id)')
                ->where('v.reply = :r AND v.value = 1')
                ->setParameter('r', $reply)
                ->getQuery()
                ->getSingleScalarResult();

            $down = (int) $voteRepo->createQueryBuilder('v')
                ->select('COUNT(v.id)')
                ->where('v.reply = :r AND v.value = -1')
                ->setParameter('r', $reply)
                ->getQuery()
                ->getSingleScalarResult();

            $replyCounts[$reply->getId()] = ['up' => $up, 'down' => $down];
        }

        // post totals
        $postUp = (int) $voteRepo->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.post = :p AND v.value = 1')
            ->setParameter('p', $post)
            ->getQuery()
            ->getSingleScalarResult();

        $postDown = (int) $voteRepo->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.post = :p AND v.value = -1')
            ->setParameter('p', $post)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('pages/community/show.html.twig', [
            'post' => $post,
            'user' => $user,
            'userPostVote' => $userPostVote,
            'replyUserVotes' => $replyUserVotes,
            'replyCounts' => $replyCounts,
            'postCounts' => ['up' => $postUp, 'down' => $postDown],
        ]);
    }

    #[Route('/community/{id}/reply', name: 'community_reply', methods: ['POST'])]
    public function reply(Request $request, Post $post): RedirectResponse
    {
        $content = trim((string) $request->request->get('content', ''));
        if ($content === '') {
            $this->addFlash('danger', 'Reply content cannot be empty.');
            return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
        }

        // static UI user name
        $user = $this->getStaticUser();
        $authorName = $user['displayName'] ?? ($user['username'] ?? 'Guest');

        $reply = new Reply();
        $reply->setPost($post);
        $reply->setContent($content);
        $reply->setAuthorName($authorName);

        $this->em->persist($reply);
        $this->em->flush();

        $this->addFlash('success', 'Your reply was posted.');

        return $this->redirectToRoute('community_post', ['id' => $post->getId()]);
    }

#[Route('/community/{id}/vote', name: 'community_vote_post', methods: ['POST'])]
public function votePost(Request $request, Post $post): JsonResponse
{
    // Use the service entity manager property ($this->em) already injected in constructor
    $em = $this->em;

    $action = $request->request->get('action', 'up');
    $value = ($action === 'up') ? 1 : -1;

    // NOTE: static user until you implement real users
    $user = $this->getStaticUser();
    $uid = $user['username'] ?? ('guest_' . session_id());

    $voteRepo = $em->getRepository(Vote::class);

    // Find existing vote row for this user + post
    $existing = $voteRepo->findOneBy(['userIdentifier' => $uid, 'post' => $post]);

    if ($existing) {
        if ($existing->getValue() === $value) {
            // same vote clicked again => remove (toggle off)
            $em->remove($existing);
            $userVote = 0;
        } else {
            // switch vote (up -> down or down -> up)
            $existing->setValue($value);
            $em->persist($existing);
            $userVote = $value;
        }
    } else {
        // create new vote
        $v = new Vote();
        $v->setUserIdentifier($uid)
          ->setValue($value)
          ->setPost($post)
          ->setReply(null);
        $em->persist($v);
        $userVote = $value;
    }

    $em->flush();

    // totals: count up and down separately
    $qb = $voteRepo->createQueryBuilder('v')
        ->select('SUM(CASE WHEN v.value = 1 THEN 1 ELSE 0 END) as upCount, SUM(CASE WHEN v.value = -1 THEN 1 ELSE 0 END) as downCount')
        ->where('v.post = :p')
        ->setParameter('p', $post);

    // execute raw (DB-agnostic)
    $row = (array) $qb->getQuery()->getSingleResult();

    $up = (int) ($row['upCount'] ?? 0);
    $down = (int) ($row['downCount'] ?? 0);

    return $this->json([
        'ok' => true,
        'upvotes' => $up,
        'downvotes' => $down,
        'userVote' => $userVote
    ]);
}

#[Route('/community/reply/{id}/vote', name: 'community_vote_reply', methods: ['POST'])]
public function voteReply(Request $request, Reply $reply): JsonResponse
{
    $em = $this->em;
    $action = $request->request->get('action', 'up');
    $value = ($action === 'up') ? 1 : -1;

    $user = $this->getStaticUser();
    $uid = $user['username'] ?? ('guest_' . session_id());

    $voteRepo = $em->getRepository(Vote::class);

    $existing = $voteRepo->findOneBy(['userIdentifier' => $uid, 'reply' => $reply]);

    if ($existing) {
        if ($existing->getValue() === $value) {
            $em->remove($existing);
            $userVote = 0;
        } else {
            $existing->setValue($value);
            $em->persist($existing);
            $userVote = $value;
        }
    } else {
        $v = new Vote();
        $v->setUserIdentifier($uid)
          ->setValue($value)
          ->setReply($reply)
          ->setPost(null);
        $em->persist($v);
        $userVote = $value;
    }

    $em->flush();

    $qb = $voteRepo->createQueryBuilder('v')
        ->select('SUM(CASE WHEN v.value = 1 THEN 1 ELSE 0 END) as upCount, SUM(CASE WHEN v.value = -1 THEN 1 ELSE 0 END) as downCount')
        ->where('v.reply = :r')
        ->setParameter('r', $reply);

    $row = (array) $qb->getQuery()->getSingleResult();

    $up = (int) ($row['upCount'] ?? 0);
    $down = (int) ($row['downCount'] ?? 0);

    return $this->json([
        'ok' => true,
        'upvotes' => $up,
        'downvotes' => $down,
        'userVote' => $userVote
    ]);
}
}
