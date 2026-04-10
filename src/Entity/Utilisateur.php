<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use App\Repository\UtilisateurRepository;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateurs')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nom = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $email = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $mdp = null;

    public function getMdp(): ?string
    {
        return $this->mdp;
    }

    public function setMdp(string $mdp): self
    {
        $this->mdp = $mdp;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $role = null;

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $skills = null;

    public function getSkills(): ?string
    {
        return $this->skills;
    }

    public function setSkills(?string $skills): self
    {
        $this->skills = $skills;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $image = null;

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $authMethod = null;

    public function getAuthMethod(): ?string
    {
        return $this->authMethod;
    }

    public function setAuthMethod(string $authMethod): self
    {
        $this->authMethod = $authMethod;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $statutCompte = null;

    public function getStatutCompte(): ?string
    {
        return $this->statutCompte;
    }

    public function setStatutCompte(?string $statutCompte): self
    {
        $this->statutCompte = $statutCompte;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Reclamation::class, mappedBy: 'utilisateur')]
    private Collection $reclamations;

    public function __construct()
    {
        $this->reclamations = new ArrayCollection();
    }

    /**
     * @return Collection<int, Reclamation>
     */
    public function getReclamations(): Collection
    {
        if (!$this->reclamations instanceof Collection) {
            $this->reclamations = new ArrayCollection();
        }
        return $this->reclamations;
    }

    public function addReclamation(Reclamation $reclamation): self
    {
        if (!$this->getReclamations()->contains($reclamation)) {
            $this->getReclamations()->add($reclamation);
        }
        return $this;
    }

    public function removeReclamation(Reclamation $reclamation): self
    {
        $this->getReclamations()->removeElement($reclamation);
        return $this;
    }

    // ======================================================
    // MÉTHODES REQUISES PAR SYMFONY POUR LA SÉCURITÉ
    // ======================================================

    /**
     * Identifiant visuel de l'utilisateur (utilisé pour la connexion)
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email; 
    }

    /**
     * Les rôles de l'utilisateur (Admin, User, etc.)
     */
    public function getRoles(): array
    {
        // On récupère le rôle de votre base de données
        $roles = [$this->role];
        
        // Garantie que chaque utilisateur a au moins le rôle standard
        if (!in_array('ROLE_USER', $roles)) {
            $roles[] = 'ROLE_USER';
        }

        return array_unique($roles);
    }

    /**
     * Récupère le mot de passe haché pour le comparer
     */
    public function getPassword(): ?string
    {
        // Chez vous, la colonne s'appelle "mdp" et non "password"
        return $this->mdp; 
    }
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Nettoie les données sensibles temporaires (obligatoire mais on le laisse vide)
     */
    public function eraseCredentials(): void
    {
        // Si vous stockiez le mot de passe en clair temporairement, on le viderait ici
    }
}