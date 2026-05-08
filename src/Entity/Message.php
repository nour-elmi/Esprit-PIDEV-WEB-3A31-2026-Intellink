<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id = 0; // CHANGEMENT: evite property.unusedType sur id Doctrine auto-genere

    // La conversation Ã  laquelle appartient ce message
    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Conversation $conversation = null;

    // L'utilisateur qui a envoyÃ© le message (ExpÃ©diteur)
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $expediteur = null;

    // Le texte du message
    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenu = null;

    // La date et l'heure d'envoi
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        // On initialise la date Ã  l'instant prÃ©sent dÃ¨s la crÃ©ation de l'objet
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getExpediteur(): ?Utilisateur
    {
        return $this->expediteur;
    }

    public function setExpediteur(?Utilisateur $expediteur): static
    {
        $this->expediteur = $expediteur;
        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRead = false; // CHANGEMENT: non-null, valeur par defaut

    public function getIsRead(): bool { return $this->isRead; } // CHANGEMENT: type de retour non-null
    public function setIsRead(bool $isRead): static { $this->isRead = $isRead; return $this; }

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachment = null;

    public function getAttachment(): ?string { return $this->attachment; }
    public function setAttachment(?string $attachment): static { $this->attachment = $attachment; return $this; }
}


