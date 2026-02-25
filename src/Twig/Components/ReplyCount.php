<?php

namespace App\Twig\Components;

use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ReplyCount
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public int $postId;

    #[LiveProp]
    public int $count = 0;

    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function mount(int $postId): void
    {
        $this->postId = $postId;
        $this->loadCount();
    }

    private function loadCount(): void
    {
        $post = $this->em->getRepository(Post::class)->find($this->postId);
        if ($post) {
            $this->count = $post->getReplies()->count();
        }
    }
}
