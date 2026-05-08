<?php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\FriendRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FriendRequestRepository::class)]
#[ApiResource]
class FriendRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id = 0; // CHANGEMENT: evite property.unusedType sur id Doctrine auto-genere

    // Celui qui envoie la demande
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)] 
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $requester = null;

    // Celui qui reÃ§oit la demande
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $receiver = null;

    #[ORM\Column(length: 20)]
    private string $status = 'PENDING'; // CHANGEMENT: non-null, valeur par defaut (PENDING, ACCEPTED, REJECTED)

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }
    public function getRequester(): ?Utilisateur { return $this->requester; }
    public function setRequester(?Utilisateur $requester): self { $this->requester = $requester; return $this; }
    public function getReceiver(): ?Utilisateur { return $this->receiver; }
    public function setReceiver(?Utilisateur $receiver): self { $this->receiver = $receiver; return $this; }
    public function getStatus(): string { return $this->status; } // CHANGEMENT: type de retour non-null
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}


