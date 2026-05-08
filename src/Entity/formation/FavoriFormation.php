<?php

namespace App\Entity\formation;

use App\Repository\FavoriFormationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FavoriFormationRepository::class)]
#[ORM\Table(name: 'favori_formation')]
#[ORM\UniqueConstraint(name: 'uniq_favori_user_formation', columns: ['idUtilisateur', 'formation_id'])]
class FavoriFormation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idFavori', type: 'integer')]
    private int $idFavori = 0; // CHANGEMENT: evite property.unusedType sur id Doctrine auto-genere

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'idFormation', nullable: false, onDelete: 'CASCADE')]
    private ?Formation $formation = null;

    #[ORM\Column(name: 'idUtilisateur', type: 'integer')]
    private ?int $idUtilisateur = null;

    #[ORM\Column(name: 'dateAjout', type: 'datetime')]
    private ?\DateTimeInterface $dateAjout = null;

    public function getIdFavori(): ?int
    {
        return $this->idFavori;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;

        return $this;
    }

    public function getIdUtilisateur(): ?int
    {
        return $this->idUtilisateur;
    }

    public function setIdUtilisateur(int $idUtilisateur): self
    {
        $this->idUtilisateur = $idUtilisateur;

        return $this;
    }

    public function getDateAjout(): ?\DateTimeInterface
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTimeInterface $dateAjout): self
    {
        $this->dateAjout = $dateAjout;

        return $this;
    }
}



