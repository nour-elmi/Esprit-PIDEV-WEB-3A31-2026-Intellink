<?php

namespace App\Controller\Concern;

use App\Entity\User;
use App\Repository\UserRepository;

trait ResolvesForumUser
{
    private function getForumUser(UserRepository $userRepository): ?User
    {
        $securityUser = $this->getUser();

        if ($securityUser instanceof User) {
            return $securityUser;
        }

        if (!is_object($securityUser) || !method_exists($securityUser, 'getId')) {
            return null;
        }

        $userId = $securityUser->getId();
        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        return $userRepository->find((int) $userId);
    }
}
