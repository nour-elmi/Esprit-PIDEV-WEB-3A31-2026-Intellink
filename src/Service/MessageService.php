<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Utilisateur;

class MessageService
{
    private array $forbiddenWords = ['spam', 'insulte', 'arnaque', 'haine'];

    /**
     * Règle Métier 1 : Un message doit être valide
     * Il doit obligatoirement avoir soit du texte, soit une pièce jointe.
     * Si c'est du texte, il ne doit pas dépasser 2000 caractères.
     */
    public function isMessageValid(Message $message): bool
    {
        $hasContent = !empty(trim((string)$message->getContenu()));
        $hasAttachment = !empty($message->getAttachment());

        // Doit avoir au moins un contenu ou une pièce jointe
        if (!$hasContent && !$hasAttachment) {
            return false;
        }

        // La longueur du contenu texte doit être respectée
        if ($hasContent && strlen($message->getContenu()) > 2000) {
            return false;
        }

        return true;
    }

    /**
     * Règle Métier 2 : Filtrage du contenu
     * Aucun message ne doit contenir des mots offensants ou considérés comme du spam.
     */
    public function containsForbiddenWords(Message $message): bool
    {
        if (empty($message->getContenu())) {
            return false;
        }

        $contentLower = strtolower($message->getContenu());
        foreach ($this->forbiddenWords as $word) {
            if (str_contains($contentLower, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Règle Métier 3 : Logique de lecture d'un message
     * Un message ne peut être marqué comme lu QUE par le destinataire (ou quelqu'un d'autre que l'expéditeur).
     * L'expéditeur ne peut pas marquer son propre message comme lu à la place du destinataire.
     */
    public function markAsRead(Message $message, Utilisateur $reader): bool
    {
        // On vérifie que l'utilisateur qui essaye de lire n'est pas l'expéditeur du message
        if ($message->getExpediteur() === $reader) {
            return false;
        }

        $message->setIsRead(true);
        return true;
    }
}
