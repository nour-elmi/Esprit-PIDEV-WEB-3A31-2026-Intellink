<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Twig\AppExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    #[Route('/api/notifications/poll', name: 'api_notifications_poll', methods: ['GET'])]
    public function poll(AppExtension $appExtension): JsonResponse
    {
        // CHANGEMENT: typer explicitement l'utilisateur connecte pour PHPStan.
        // Ancien code (garde):
        // $user = $this->getUser();
        // if (!$user) {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // Count Reclamations
        $unreadRecs = 0;
        // CHANGEMENT: getReclamations() retourne deja une collection non-null.
        // Ancien code (garde): foreach ($user->getReclamations() ?? [] as $reclamation) {
        foreach ($user->getReclamations() as $reclamation) {
            if ($reclamation->getStatut() === 'TRAITE' && !$reclamation->getIsRead()) {
                $unreadRecs++;
            }
        }

        // Count Friend Requests
        $pendingFriends = count($appExtension->getPendingFriendRequests($user));

        // Messenger is typically handled by its own JS but for a global count we could include it if we had it,
        // however we will let messenger continue its own logic or merge it on client side.
        
        $totalNotifs = $unreadRecs + $pendingFriends;

        return $this->json([
            'unreadRecs' => $unreadRecs,
            'pendingFriends' => $pendingFriends,
            'totalNotifs' => $totalNotifs
        ]);
    }
}
