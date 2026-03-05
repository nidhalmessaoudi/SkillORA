<?php

namespace App\Tests\Community\Entity;

use App\Entity\Post;
use App\Entity\Reaction;
use App\Entity\Reply;
use PHPUnit\Framework\TestCase;

class ReactionTest extends TestCase
{
    public function testAllAvailableReactionTypesAreAccepted(): void
    {
        foreach (Reaction::AVAILABLE_TYPES as $type) {
            $reaction = new Reaction();
            $reaction->setType($type);

            self::assertSame($type, $reaction->getType());
        }
    }

    public function testInvalidReactionTypeThrowsException(): void
    {
        $reaction = new Reaction();

        $this->expectException(\InvalidArgumentException::class);
        $reaction->setType('invalid');
    }

    public function testReactionCanTargetPostOrReply(): void
    {
        $post = new Post();
        $reply = new Reply();
        $reaction = new Reaction();

        $reaction->setPost($post);
        self::assertSame($post, $reaction->getPost());

        $reaction->setReply($reply);
        self::assertSame($reply, $reaction->getReply());
    }
}
