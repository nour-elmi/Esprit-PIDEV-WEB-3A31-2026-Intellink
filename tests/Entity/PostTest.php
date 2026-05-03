<?php

namespace App\Tests\Entity;

use App\Entity\Post;
use PHPUnit\Framework\TestCase;

final class PostTest extends TestCase
{
    public function testPostSettersAndGetters(): void
    {
        $post = new Post();

        $post->setContent('Test post content');
        $post->setStatus('ACTIVE');
        $post->setIsEdited(false);
        $post->setIsPinned(false);
        $post->setIsLocked(false);

        self::assertSame('Test post content', $post->getContent());
        self::assertSame('ACTIVE', $post->getStatus());
        self::assertFalse($post->isEdited());
        self::assertFalse($post->isPinned());
        self::assertFalse($post->isLocked());
    }
}