<?php

namespace App\Controller;

use App\Controller\Concern\ResolvesForumUser;
use App\Entity\Reaction;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ReactionController extends AbstractController
{
    use ResolvesForumUser;

    #[Route('/reaction/{id}/{type}', name: 'app_reaction_vote')]
    public function vote(
        int $id,
        string $type,
        PostRepository $postRepository,
        ReactionRepository $reactionRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        if (!in_array($type, ['UP', 'DOWN'])) {
            return $this->redirectToRoute('app_forum');
        }

        $post = $postRepository->find($id);
        $user = $this->getForumUser($userRepository);

        if (!$post || !$user) {
            $this->addFlash('error', 'Vous devez etre connecte pour reagir.');
            return $this->redirectToRoute('app_login');
        }

        $existingReaction = $reactionRepository->findOneBy([
            'post' => $post,
            'author' => $user,
        ]);

        if ($existingReaction) {
            if ($existingReaction->getType() === $type) {
                // same click again => remove reaction
                $entityManager->remove($existingReaction);
            } else {
                // switch UP <-> DOWN
                $existingReaction->setType($type);
                $entityManager->persist($existingReaction);
            }
        } else {
            $reaction = new Reaction();
            $reaction->setPost($post);
            $reaction->setAuthor($user);
            $reaction->setType($type);

            $entityManager->persist($reaction);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_forum');
    }
}
