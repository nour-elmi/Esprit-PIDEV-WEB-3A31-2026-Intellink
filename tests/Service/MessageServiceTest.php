<?php

namespace App\Tests\Service;

use App\Entity\Message;
use App\Entity\Utilisateur;
use App\Service\MessageService;
use PHPUnit\Framework\TestCase;

class MessageServiceTest extends TestCase
{
    private MessageService $messageService;

    protected function setUp(): void
    {
        // Initialisation de notre service métier avant chaque test
        $this->messageService = new MessageService();
    }

    /* -------------------------------------------------------------------------
     * TESTS POUR LA REGLE 1 : isMessageValid()
     * ------------------------------------------------------------------------- */

    public function testIsMessageValidWithTextOnly(): void
    {
        $message = new Message();
        $message->setContenu('Bonjour, voici un message normal.');

        $this->assertTrue($this->messageService->isMessageValid($message));
    }

    public function testIsMessageValidWithAttachmentOnly(): void
    {
        $message = new Message();
        $message->setContenu('');
        $message->setAttachment('document.pdf');

        $this->assertTrue($this->messageService->isMessageValid($message));
    }

    public function testIsMessageInvalidIfEmpty(): void
    {
        $message = new Message();
        $message->setContenu(''); // Pas de contenu
        // Pas de pièce jointe par défaut

        $this->assertFalse($this->messageService->isMessageValid($message));
    }

    public function testIsMessageInvalidIfTooLong(): void
    {
        $message = new Message();
        // Création d'une chaîne de 2001 caractères
        $message->setContenu(str_repeat('a', 2001));

        $this->assertFalse($this->messageService->isMessageValid($message));
    }

    /* -------------------------------------------------------------------------
     * TESTS POUR LA REGLE 2 : containsForbiddenWords()
     * ------------------------------------------------------------------------- */

    public function testContainsForbiddenWordsReturnsTrue(): void
    {
        $message = new Message();
        $message->setContenu('Ceci est une grosse arnaque !');

        $this->assertTrue($this->messageService->containsForbiddenWords($message));
    }

    public function testContainsForbiddenWordsReturnsFalseForCleanText(): void
    {
        $message = new Message();
        $message->setContenu('Ceci est un projet génial.');

        $this->assertFalse($this->messageService->containsForbiddenWords($message));
    }

    /* -------------------------------------------------------------------------
     * TESTS POUR LA REGLE 3 : markAsRead()
     * ------------------------------------------------------------------------- */

    public function testMarkAsReadByRecipientIsSuccessful(): void
    {
        $expediteur = new Utilisateur();
        $destinataire = clone clone $expediteur; // Simuler deux instances distinctes

        $message = new Message();
        $message->setExpediteur($expediteur);
        $message->setIsRead(false);

        $result = $this->messageService->markAsRead($message, clone $destinataire);

        // Doit retourner true et le message doit être marqué comme lu
        $this->assertTrue($result);
        $this->assertTrue($message->getIsRead());
    }

    public function testMarkAsReadBySenderShouldFail(): void
    {
        $expediteur = new Utilisateur();

        $message = new Message();
        $message->setExpediteur($expediteur);
        $message->setIsRead(false);

        // L'expéditeur essaie de marquer son propre message envoyé comme lu
        $result = $this->messageService->markAsRead($message, $expediteur);

        // Doit retourner false et le message reste non lu
        $this->assertFalse($result);
        $this->assertFalse($message->getIsRead());
    }
}
