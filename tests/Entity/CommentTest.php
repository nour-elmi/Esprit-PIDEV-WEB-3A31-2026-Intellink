<?php

namespace App\Tests\Entity;

use App\Entity\Comment;
use PHPUnit\Framework\TestCase;

final class CommentTest extends TestCase
{
    public function testCommentSettersAndGetters(): void
    {
        $comment = new Comment();

        $comment->setContent('Test comment');
        $comment->setStatus('ACTIVE');
        $comment->setIsEdited(false);

        self::assertSame('Test comment', $comment->getContent());
        self::assertSame('ACTIVE', $comment->getStatus());
        self::assertFalse($comment->isEdited());
    }
}