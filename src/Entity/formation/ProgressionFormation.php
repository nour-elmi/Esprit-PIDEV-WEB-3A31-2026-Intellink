<?php

namespace App\Entity\formation;

use App\Repository\ProgressionFormationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgressionFormationRepository::class)]
#[ORM\Table(name: 'progression_formation')]
#[ORM\UniqueConstraint(name: 'uniq_progression_participation', columns: ['participation_id'])]
class ProgressionFormation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idProgression', type: 'integer')]
    private int $idProgression = 0; // CHANGEMENT: evite property.unusedType sur id Doctrine auto-genere

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'idFormation', nullable: false, onDelete: 'CASCADE')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(targetEntity: Participation::class)]
    #[ORM\JoinColumn(name: 'participation_id', referencedColumnName: 'idParticipation', nullable: false, onDelete: 'CASCADE')]
    private ?Participation $participation = null;

    #[ORM\Column(name: 'idUtilisateur', type: 'integer')]
    private ?int $idUtilisateur = null;

    #[ORM\Column(name: 'pourcentage', type: 'float')]
    private float $pourcentage = 0.0;

    #[ORM\Column(name: 'video_seconds', type: 'integer')]
    private int $videoSeconds = 0;

    #[ORM\Column(name: 'video_duration', type: 'integer')]
    private int $videoDuration = 0;

    #[ORM\Column(name: 'quiz_score', type: 'float', nullable: true)]
    private ?float $quizScore = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 20)]
    private string $statut = 'non_commence';

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function getIdProgression(): ?int
    {
        return $this->idProgression;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(Formation $formation): self
    {
        $this->formation = $formation;

        return $this;
    }

    public function getParticipation(): ?Participation
    {
        return $this->participation;
    }

    public function setParticipation(Participation $participation): self
    {
        $this->participation = $participation;

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

    public function getPourcentage(): float
    {
        return $this->pourcentage;
    }

    public function setPourcentage(float $pourcentage): self
    {
        $this->pourcentage = $pourcentage;

        return $this;
    }

    public function getVideoSeconds(): int
    {
        return $this->videoSeconds;
    }

    public function setVideoSeconds(int $videoSeconds): self
    {
        $this->videoSeconds = $videoSeconds;

        return $this;
    }

    public function getVideoDuration(): int
    {
        return $this->videoDuration;
    }

    public function setVideoDuration(int $videoDuration): self
    {
        $this->videoDuration = $videoDuration;

        return $this;
    }

    public function getQuizScore(): ?float
    {
        return $this->quizScore;
    }

    public function setQuizScore(?float $quizScore): self
    {
        $this->quizScore = $quizScore;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;

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


