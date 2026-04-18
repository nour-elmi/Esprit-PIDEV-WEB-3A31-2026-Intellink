<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'action_log')]
class ActionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'actor_id', type: 'integer')]
    private ?int $actorId = null;

    #[ORM\Column(name: 'projet_id', type: 'integer', nullable: true)]
    private ?int $projetId = null;

    #[ORM\Column(name: 'action', type: 'string', length: 40)]
    private ?string $action = null;

    #[ORM\Column(name: 'details', type: 'string', length: 255)]
    private ?string $details = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int { return $this->id; }

    public function getActorId(): ?int { return $this->actorId; }
    public function setActorId(int $actorId): self { $this->actorId = $actorId; return $this; }

    public function getProjetId(): ?int { return $this->projetId; }
    public function setProjetId(?int $projetId): self { $this->projetId = $projetId; return $this; }

    public function getAction(): ?string { return $this->action; }
    public function setAction(string $action): self { $this->action = $action; return $this; }

    public function getDetails(): ?string { return $this->details; }
    public function setDetails(string $details): self { $this->details = $details; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
