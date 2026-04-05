<?php

namespace App\Entity;

use App\Repository\ListeParticipationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListeParticipationRepository::class)]
class ListeParticipation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_participation = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date_participation = null;

    #[ORM\Column(name: "statut", type: "string", enumType: Statutt::class)]
    private ?Statutt $statutt = Statutt::EN_ATTENTE;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_reponse = null;

    #[ORM\Column(nullable: true)]
    private ?int $id_offre = null;

    #[ORM\Column(nullable: true)]
    private ?int $id_user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom_p = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prenom_p = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cv = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $skills = null;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $score = 0;

    // --- GETTERS & SETTERS ---

    public function getIdParticipation(): ?int
    {
        return $this->id_participation;
    }

    public function getDateParticipation(): ?\DateTimeInterface
    {
        return $this->date_participation;
    }

    public function setDateParticipation(\DateTimeInterface $date_participation): static
    {
        $this->date_participation = $date_participation;
        return $this;
    }

    public function getStatutt(): ?Statutt
    {
        return $this->statutt;
    }

    public function setStatutt(Statutt $statutt): static
    {
        $this->statutt = $statutt;
        return $this;
    }

    public function getDateReponse(): ?\DateTimeInterface
    {
        return $this->date_reponse;
    }

    public function setDateReponse(?\DateTimeInterface $date_reponse): static
    {
        $this->date_reponse = $date_reponse;
        return $this;
    }

    public function getIdOffre(): ?int
    {
        return $this->id_offre;
    }

    public function setIdOffre(?int $id_offre): static
    {
        $this->id_offre = $id_offre;
        return $this;
    }

    public function getIdUser(): ?int
    {
        return $this->id_user;
    }

    public function setIdUser(?int $id_user): static
    {
        $this->id_user = $id_user;
        return $this;
    }

    public function getNomP(): ?string
    {
        return $this->nom_p;
    }

    public function setNomP(?string $nom_p): static
    {
        $this->nom_p = $nom_p;
        return $this;
    }

    public function getPrenomP(): ?string
    {
        return $this->prenom_p;
    }

    public function setPrenomP(?string $prenom_p): static
    {
        $this->prenom_p = $prenom_p;
        return $this;
    }

    public function getCv(): ?string
    {
        return $this->cv;
    }

    public function setCv(?string $cv): static
    {
        $this->cv = $cv;
        return $this;
    }

    public function getSkills(): ?string
    {
        return $this->skills;
    }

    public function setSkills(?string $skills): static
    {
        $this->skills = $skills;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;
        return $this;
    }
}