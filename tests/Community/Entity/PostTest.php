<?php

namespace App\Tests\Community\Entity;

use App\Entity\Post;
use App\Entity\Reply;
use App\Entity\Tag;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
    public function testAddAndCountReplies(): void
    {
        $post = new Post();
        $reply = new Reply();

        $post->addReply($reply);

        self::assertSame(1, $post->getReplyCount());
        self::assertSame($post, $reply->getPost());
    }

    public function testRemoveReplyDecrementsCount(): void
    {
        $post = new Post();
        $reply = new Reply();
        $post->addReply($reply);

        $post->removeReply($reply);

        self::assertSame(0, $post->getReplyCount());
    }

    public function testAddTagIsUnique(): void
    {
        $post = new Post();
        $tag = (new Tag())->setName('symfony')->setSlug('symfony');

        $post->addTag($tag);
        $post->addTag($tag);

        self::assertCount(1, $post->getTags());
    }

    public function testLifecycleCallbacksSetDates(): void
    {
        $post = new Post();

        $post->onPrePersist();
        self::assertNotNull($post->getCreatedAt());

        $post->onPreUpdate();
        self::assertNotNull($post->getUpdatedAt());
    }

    public function testCoreFieldsCanBeSetForCommunityPost(): void
    {
        $author = (new User())
            ->setEmail('community@example.com')
            ->setUsername('community_user')
            ->setPassword('hashed-password');

        $post = (new Post())
            ->setType('question')
            ->setTitle('How to optimize Doctrine query?')
            ->setTopic('Doctrine ORM')
            ->setContent('I need help with performance in my post listing.')
            ->setAuthor($author);

        self::assertSame('question', $post->getType());
        self::assertSame('How to optimize Doctrine query?', $post->getTitle());
        self::assertSame('Doctrine ORM', $post->getTopic());
        self::assertSame('I need help with performance in my post listing.', $post->getContent());
        self::assertSame($author, $post->getAuthor());
    }
}
