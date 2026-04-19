<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ReclamationRepository;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
#[ORM\Table(name: 'reclamations')]
class Reclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'reclamations')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $objet = null;

    public function getObjet(): ?string
    {
        return $this->objet;
    }

    public function setObjet(string $objet): self
    {
        $this->objet = $objet;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $type = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function estimerPriorite(): string
    {
        $descriptionLower = strtolower($this->description ?? '');
        $objetLower = strtolower($this->objet ?? '');
        $typeLower = strtolower($this->type ?? '');
        $fileContentLocal = '';

        // Essayer de lire le contenu de la pièce jointe
        if ($this->piece_jointe) {
            $filePath = __DIR__ . '/../../public/uploads/reclamations/' . $this->piece_jointe;
            if (file_exists($filePath)) {
                // Lecture partielle (jusqu'à 50 ko) pour détecter des potentiels mots clés textes (fonctionne particulièrement si le PDF n'est pas compressé, pour les autres formats texte)
                $fileContentLocal = strtolower(file_get_contents($filePath, false, null, 0, 50000) ?: '');
            }
        }

        // Mots clés qui indiquent une urgence élevée
        $motsClesUrgents = ['urgent', 'immédiat', 'panne', 'bloqué', 'impossible', 'erreur fatale', 'critique', 'crash', 'piratage'];
        
        // Mots clés pour priorité moyenne
        $motsClesMoyens = ['problème', 'bug', 'ralentissement', 'facturation', 'paiement', 'erreur', 'ne fonctionne pas'];

        $score = 0;

        if ($typeLower === 'technique') {
            $score += 2;
        }

        foreach ($motsClesUrgents as $mot) {
            if (str_contains($descriptionLower, $mot) || str_contains($objetLower, $mot) || str_contains($fileContentLocal, $mot)) {
                $score += 5;
            }
        }

        foreach ($motsClesMoyens as $mot) {
            if (str_contains($descriptionLower, $mot) || str_contains($objetLower, $mot) || str_contains($fileContentLocal, $mot)) {
                $score += 2;
            }
        }

        if ($score >= 5) {
            return 'Urgent';
        } elseif ($score >= 2) {
            return 'Moyenne';
        }
        
        return 'Normale';
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $priorite = null;

    public function getPriorite(): ?string
    {
        return $this->priorite;
    }

    public function setPriorite(?string $priorite): self
    {
        $this->priorite = $priorite;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $statut = null;

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reponse_admin = null;

    public function getReponse_admin(): ?string
    {
        return $this->reponse_admin;
    }

    public function setReponse_admin(?string $reponse_admin): self
    {
        $this->reponse_admin = $reponse_admin;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_creation = null;

    public function getDate_creation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDate_creation(?\DateTimeInterface $date_creation): self
    {
        $this->date_creation = $date_creation;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $piece_jointe = null;

    public function getPiece_jointe(): ?string
    {
        return $this->piece_jointe;
    }

    public function setPiece_jointe(?string $piece_jointe): self
    {
        $this->piece_jointe = $piece_jointe;
        return $this;
    }

    public function getReponseAdmin(): ?string
    {
        return $this->reponse_admin;
    }

    public function setReponseAdmin(?string $reponse_admin): static
    {
        $this->reponse_admin = $reponse_admin;

        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->date_creation;
    }

    public function setDateCreation(?\DateTime $date_creation): static
    {
        $this->date_creation = $date_creation;

        return $this;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    public function getPieceJointe(): ?string
    {
        return $this->piece_jointe;
    }

    public function setPieceJointe(?string $piece_jointe): static
    {
        $this->piece_jointe = $piece_jointe;

        return $this;
    }

    public function getIsRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

}
