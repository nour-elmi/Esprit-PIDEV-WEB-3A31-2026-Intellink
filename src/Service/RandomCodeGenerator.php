<?php

namespace App\Service;

final class RandomCodeGenerator implements CodeGeneratorInterface
{
    public function generate(): string
    {
        return sprintf('%06d', mt_rand(1, 999999));
    }
}
