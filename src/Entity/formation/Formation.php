<?php

namespace App\Entity\formation;

use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\Table(name: 'formation')]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idFormation', type: 'integer')]
    private ?int $idFormation = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $domaine = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $niveau = null;

    #[ORM\Column(name: 'urlVideo' ,type: 'string', length: 255)]
    private ?string $urlVideo = null;

    #[ORM\Column(name: 'idFormateur', type: 'integer', nullable: true)]
    private ?int $idFormateur = null;

    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'formation')]
    private Collection $participations;

    #[ORM\OneToMany(targetEntity: Quiz::class, mappedBy: 'formation')]
    private Collection $quizs;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
        $this->quizs = new ArrayCollection();
    }

    public function getIdFormation(): ?int
    {
        return $this->idFormation;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDomaine(): ?string
    {
        return $this->domaine;
    }

    public function setDomaine(?string $domaine): self
    {
        $this->domaine = $domaine;
        return $this;
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function setNiveau(?string $niveau): self
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getUrlVideo(): ?string
    {
        return $this->urlVideo;
    }

    public function setUrlVideo(string $urlVideo): self
    {
        $this->urlVideo = $urlVideo;
        return $this;
    }

    public function getIdFormateur(): ?int
    {
        return $this->idFormateur;
    }

    public function setIdFormateur(?int $idFormateur): self
    {
        $this->idFormateur = $idFormateur;
        return $this;
    }

    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): self
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setFormation($this);
        }
        return $this;
    }

    public function removeParticipation(Participation $participation): self
    {
        $this->participations->removeElement($participation);
        return $this;
    }

    public function getQuizs(): Collection
    {
        return $this->quizs;
    }

    public function addQuiz(Quiz $quiz): self
    {
        if (!$this->quizs->contains($quiz)) {
            $this->quizs->add($quiz);
            $quiz->setFormation($this);
        }
        return $this;
    }

    public function removeQuiz(Quiz $quiz): self
    {
        $this->quizs->removeElement($quiz);
        return $this;
    }
}