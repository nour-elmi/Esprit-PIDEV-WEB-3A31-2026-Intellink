<?php

namespace App\Entity;

use App\Repository\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idParticipation', type: 'integer')]
    private ?int $idParticipation = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'idFormation', referencedColumnName: 'idFormation')]
    private ?Formation $formation = null;

    #[ORM\Column(name: 'idUtilisateur', type: 'integer', nullable: true)]
    private ?int $idUtilisateur = null;

    #[ORM\Column(name: 'poste_actuel', type: 'string', length: 150, nullable: true)]
    private ?string $posteActuel = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $attentes = null;

    #[ORM\Column(name: 'dateInscription', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $score = null;

    public function getIdParticipation(): ?int { return $this->idParticipation; }

    public function getFormation(): ?Formation { return $this->formation; }
    public function setFormation(?Formation $formation): self { $this->formation = $formation; return $this; }

    public function getIdUtilisateur(): ?int { return $this->idUtilisateur; }
    public function setIdUtilisateur(?int $idUtilisateur): self { $this->idUtilisateur = $idUtilisateur; return $this; }

    public function getPosteActuel(): ?string { return $this->posteActuel; }
    public function setPosteActuel(?string $posteActuel): self { $this->posteActuel = $posteActuel; return $this; }

    public function getAttentes(): ?string { return $this->attentes; }
    public function setAttentes(?string $attentes): self { $this->attentes = $attentes; return $this; }

    public function getDateInscription(): ?\DateTimeInterface { return $this->dateInscription; }
    public function setDateInscription(?\DateTimeInterface $dateInscription): self { $this->dateInscription = $dateInscription; return $this; }

    public function getScore(): ?float { return $this->score; }
    public function setScore(?float $score): self { $this->score = $score; return $this; }
}