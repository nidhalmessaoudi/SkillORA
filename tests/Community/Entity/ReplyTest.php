<?php

namespace App\Tests\Community\Entity;

use App\Entity\Reply;
use PHPUnit\Framework\TestCase;

class ReplyTest extends TestCase
{
    public function testTopLevelReplyHasNoParentAndZeroDepth(): void
    {
        $reply = new Reply();

        self::assertTrue($reply->isTopLevel());
        self::assertSame(0, $reply->getDepth());
    }

    public function testNestedReplyDepth(): void
    {
        $parent = new Reply();
        $child = new Reply();
        $grandChild = new Reply();

        $parent->addReply($child);
        $child->addReply($grandChild);

        self::assertSame(1, $child->getDepth());
        self::assertSame(2, $grandChild->getDepth());
        self::assertFalse($grandChild->isTopLevel());
    }

    public function testRemovingChildReplyClearsParent(): void
    {
        $parent = new Reply();
        $child = new Reply();
        $parent->addReply($child);

        $parent->removeReply($child);

        self::assertNull($child->getParent());
        self::assertCount(0, $parent->getReplies());
    }
}
