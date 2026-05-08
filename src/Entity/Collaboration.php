<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'collaboration')]
class Collaboration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_collaboration', type: 'integer')]
    private int $id = 0; // CHANGEMENT: evite property.unusedType sur id Doctrine auto-genere

    #[ORM\ManyToOne(targetEntity: Projet::class)]
    #[ORM\JoinColumn(name: 'projet_id', referencedColumnName: 'id_projet', nullable: false, onDelete: 'CASCADE')]
    private ?Projet $projet = null;

    #[ORM\Column(name: 'id_user', type: 'integer')]
    private ?int $userId = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(name: 'etat', type: 'string', length: 20, options: ['default' => 'EN_ATTENTE'])]
    private string $etat = 'EN_ATTENTE'; // CHANGEMENT: non-null, valeur par defaut toujours definie

    #[ORM\Column(name: 'role_souhaite', type: 'string', length: 60, nullable: true)]
    private ?string $roleSouhaite = null;

    #[ORM\Column(name: 'motivation', type: 'text', nullable: true)]
    private ?string $motivation = null;

    #[ORM\Column(name: 'disponibilite', type: 'string', length: 60, nullable: true)]
    private ?string $disponibilite = null;

    #[ORM\Column(name: 'portfolio', type: 'string', length: 255, nullable: true)]
    private ?string $portfolio = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjet(): ?Projet
    {
        return $this->projet;
    }

    public function setProjet(?Projet $projet): self
    {
        $this->projet = $projet;
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

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTimeInterface $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getEtat(): string // CHANGEMENT: type de retour aligne sur la propriete non-null
    {
        return $this->etat;
    }

    public function setEtat(string $etat): self
    {
        $this->etat = $etat;
        return $this;
    }

    public function getRoleSouhaite(): ?string
    {
        return $this->roleSouhaite;
    }

    public function setRoleSouhaite(?string $roleSouhaite): self
    {
        $this->roleSouhaite = $roleSouhaite;
        return $this;
    }

    public function getMotivation(): ?string
    {
        return $this->motivation;
    }

    public function setMotivation(?string $motivation): self
    {
        $this->motivation = $motivation;
        return $this;
    }

    public function getDisponibilite(): ?string
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?string $disponibilite): self
    {
        $this->disponibilite = $disponibilite;
        return $this;
    }

    public function getPortfolio(): ?string
    {
        return $this->portfolio;
    }

    public function setPortfolio(?string $portfolio): self
    {
        $this->portfolio = $portfolio;
        return $this;
    }
}


