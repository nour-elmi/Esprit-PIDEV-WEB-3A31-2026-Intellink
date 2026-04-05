<?php

namespace App\Entity\formation;

use App\Repository\QuizRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Table(name: 'quiz')]
class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idQuiz', type: 'integer')]
    private ?int $idQuiz = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'quizs')]
    #[ORM\JoinColumn(name: 'idFormation', referencedColumnName: 'idFormation')]
    private ?Formation $formation = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $duree = null;

    #[ORM\Column(name: 'scoreMax', type: 'float', nullable: true)]
    private ?float $scoreMax = null;

    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'quiz')]
    private Collection $questions;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
    }

    public function getIdQuiz(): ?int { return $this->idQuiz; }

    public function getFormation(): ?Formation { return $this->formation; }
    public function setFormation(?Formation $formation): self { $this->formation = $formation; return $this; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getDuree(): ?int { return $this->duree; }
    public function setDuree(?int $duree): self { $this->duree = $duree; return $this; }

    public function getScoreMax(): ?float { return $this->scoreMax; }
    public function setScoreMax(?float $scoreMax): self { $this->scoreMax = $scoreMax; return $this; }

    public function getQuestions(): Collection { return $this->questions; }

    public function addQuestion(Question $question): self
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setQuiz($this);
        }
        return $this;
    }

    public function removeQuestion(Question $question): self
    {
        $this->questions->removeElement($question);
        return $this;
    }
}