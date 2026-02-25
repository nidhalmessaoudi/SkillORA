<?php

namespace App\Twig\Components;

use App\Entity\Post;
use App\Entity\Reaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class PostReaction
{
    use DefaultActionTrait;

    #[LiveProp]
    public int $postId;

    #[LiveProp]
    public ?string $userReaction = null;

    #[LiveProp]
    public array $counts = [];

    #[LiveProp]
    public int $total = 0;

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {
    }

    public function mount(int $postId): void
    {
        $this->postId = $postId;
        $this->loadReactionData();
    }

    #[LiveAction]
    public function react(string $type): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $post = $this->em->getRepository(Post::class)->find($this->postId);
        if (!$post) {
            return;
        }

        // Validate reaction type
        if (!in_array($type, Reaction::AVAILABLE_TYPES, true)) {
            return;
        }

        $reactionRepo = $this->em->getRepository(Reaction::class);
        $existing = $reactionRepo->findOneBy(['user' => $user, 'post' => $post]);

        if ($existing) {
            if ($existing->getType() === $type) {
                // Remove reaction if clicking same type
                $this->em->remove($existing);
                $this->userReaction = null;
            } else {
                // Change reaction type
                $existing->setType($type);
                $this->em->persist($existing);
                $this->userReaction = $type;
            }
        } else {
            // Create new reaction
            $reaction = new Reaction();
            $reaction->setUser($user)
                ->setType($type)
                ->setPost($post)
                ->setReply(null);
            $this->em->persist($reaction);
            $this->userReaction = $type;
        }

        $this->em->flush();
        $this->loadReactionData();
    }

    private function loadReactionData(): void
    {
        $user = $this->security->getUser();
        $post = $this->em->getRepository(Post::class)->find($this->postId);

        if (!$post) {
            return;
        }

        // Get user's reaction
        if ($user instanceof User) {
            $reactionRepo = $this->em->getRepository(Reaction::class);
            $userReaction = $reactionRepo->findOneBy(['user' => $user, 'post' => $post]);
            $this->userReaction = $userReaction ? $userReaction->getType() : null;
        }

        // Get reaction counts
        $qb = $this->em->getRepository(Reaction::class)->createQueryBuilder('r')
            ->select('r.type, COUNT(r.id) as count')
            ->where('r.post = :post')
            ->setParameter('post', $post)
            ->groupBy('r.type');

        $results = $qb->getQuery()->getResult();

        $this->counts = [];
        foreach ($results as $row) {
            $this->counts[$row['type']] = (int) $row['count'];
        }

        $this->total = array_sum($this->counts);
    }
}
