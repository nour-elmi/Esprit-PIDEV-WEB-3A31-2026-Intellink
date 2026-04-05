<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: 'question')]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idQuestion', type: 'integer')]
    private ?int $idQuestion = null;

    #[ORM\ManyToOne(targetEntity: Quiz::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(name: 'idQuiz', referencedColumnName: 'idQuiz')]
    private ?Quiz $quiz = null;

    #[ORM\Column(type: 'text')]
    private ?string $enonce = null;

    #[ORM\Column(name: 'reponseCorrecte', type: 'string', length: 10, nullable: true)]
    private ?string $reponseCorrecte = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $points = null;

    public function getIdQuestion(): ?int { return $this->idQuestion; }

    public function getQuiz(): ?Quiz { return $this->quiz; }
    public function setQuiz(?Quiz $quiz): self { $this->quiz = $quiz; return $this; }

    public function getEnonce(): ?string { return $this->enonce; }
    public function setEnonce(string $enonce): self { $this->enonce = $enonce; return $this; }

    public function getReponseCorrecte(): ?string { return $this->reponseCorrecte; }
    public function setReponseCorrecte(?string $reponseCorrecte): self { $this->reponseCorrecte = $reponseCorrecte; return $this; }

    public function getPoints(): ?float { return $this->points; }
    public function setPoints(?float $points): self { $this->points = $points; return $this; }
}