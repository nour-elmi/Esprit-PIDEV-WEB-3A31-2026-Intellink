<?php

namespace App\Tests\Entity;

use App\Entity\Reaction;
use PHPUnit\Framework\TestCase;

final class ReactionTest extends TestCase
{
    public function testReactionType(): void
    {
        $reaction = new Reaction();

        $reaction->setType('UP');

        self::assertSame('UP', $reaction->getType());
    }
}