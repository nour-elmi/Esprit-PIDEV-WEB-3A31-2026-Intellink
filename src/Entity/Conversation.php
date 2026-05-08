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
    private int $id = 0; // CHANGEMENT: evite property.unusedType sur id Doctrine auto-genere

    // Les deux amis qui participent au chat
    /** @var Collection<int, Utilisateur> */
    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $participants;

    /** @var Collection<int, Message> */
    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $messages;

    public function __construct() {
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    /** @return Collection<int, Utilisateur> */
    public function getParticipants(): Collection { return $this->participants; }
    public function addParticipant(Utilisateur $participant): self {
        if (!$this->participants->contains($participant)) { $this->participants->add($participant); }
        return $this;
    }
    /** @return Collection<int, Message> */
    public function getMessages(): Collection { return $this->messages; }
}


