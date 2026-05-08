<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contrat_participation')]
#[ORM\UniqueConstraint(name: 'uniq_contrat_collaboration', columns: ['collaboration_id'])]
class ContratParticipation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private int $id = 0; // CHANGEMENT: evite property.unusedType sur id Doctrine auto-genere

    #[ORM\ManyToOne(targetEntity: Collaboration::class)]
    #[ORM\JoinColumn(name: 'collaboration_id', referencedColumnName: 'id_collaboration', nullable: false, onDelete: 'CASCADE')]
    private ?Collaboration $collaboration = null;

    #[ORM\Column(name: 'projet_id', type: 'integer')]
    private ?int $projetId = null;

    #[ORM\Column(name: 'chef_id', type: 'integer')]
    private ?int $chefId = null;

    #[ORM\Column(name: 'user_id', type: 'integer')]
    private ?int $userId = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 30)]
    private string $statut = 'ENVOYE_AU_USER'; // CHANGEMENT: non-null, valeur par defaut

    #[ORM\Column(name: 'contenu', type: 'text')]
    private ?string $contenu = null;

    #[ORM\Column(name: 'user_signature_name', type: 'string', length: 120, nullable: true)]
    private ?string $userSignatureName = null;

    #[ORM\Column(name: 'user_signed_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $userSignedAt = null;

    #[ORM\Column(name: 'chef_signature_name', type: 'string', length: 120, nullable: true)]
    private ?string $chefSignatureName = null;

    #[ORM\Column(name: 'chef_signed_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $chefSignedAt = null;

    #[ORM\Column(name: 'expires_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCollaboration(): ?Collaboration
    {
        return $this->collaboration;
    }

    public function setCollaboration(?Collaboration $collaboration): self
    {
        $this->collaboration = $collaboration;
        return $this;
    }

    public function getProjetId(): ?int
    {
        return $this->projetId;
    }

    public function setProjetId(int $projetId): self
    {
        $this->projetId = $projetId;
        return $this;
    }

    public function getChefId(): ?int
    {
        return $this->chefId;
    }

    public function setChefId(int $chefId): self
    {
        $this->chefId = $chefId;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getStatut(): string // CHANGEMENT: type de retour non-null
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getUserSignatureName(): ?string
    {
        return $this->userSignatureName;
    }

    public function setUserSignatureName(?string $userSignatureName): self
    {
        $this->userSignatureName = $userSignatureName;
        return $this;
    }

    public function getUserSignedAt(): ?\DateTimeInterface
    {
        return $this->userSignedAt;
    }

    public function setUserSignedAt(?\DateTimeInterface $userSignedAt): self
    {
        $this->userSignedAt = $userSignedAt;
        return $this;
    }

    public function getChefSignatureName(): ?string
    {
        return $this->chefSignatureName;
    }

    public function setChefSignatureName(?string $chefSignatureName): self
    {
        $this->chefSignatureName = $chefSignatureName;
        return $this;
    }

    public function getChefSignedAt(): ?\DateTimeInterface
    {
        return $this->chefSignedAt;
    }

    public function setChefSignedAt(?\DateTimeInterface $chefSignedAt): self
    {
        $this->chefSignedAt = $chefSignedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}



