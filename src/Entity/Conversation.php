<?php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ApiResource]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Les deux amis qui participent au chat
    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $participants;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class, orphanRemoval: true)]
    private Collection $messages;

    public function __construct() {
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getParticipants(): Collection { return $this->participants; }
    public function addParticipant(Utilisateur $participant): self {
        if (!$this->participants->contains($participant)) { $this->participants->add($participant); }
        return $this;
    }
    public function getMessages(): Collection { return $this->messages; }
}