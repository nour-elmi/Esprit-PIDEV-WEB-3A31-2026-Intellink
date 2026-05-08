<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "postId")]
    private int $id = 0; // CHANGEMENT: evite property.unusedType sur id Doctrine auto-genere

    #[ORM\Column(name: "content", length: 1000)]
    private ?string $content = null;

    #[ORM\Column(name: "status", length: 100)]
    private string $status = 'PUBLISHED'; // CHANGEMENT: non-null, valeur par defaut

    #[ORM\Column(name: "createdAt")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: "isEdited")]
    private bool $isEdited = false; // CHANGEMENT: non-null, valeur par defaut

    #[ORM\Column(name: "isPinned")]
    private bool $isPinned = false; // CHANGEMENT: non-null, valeur par defaut

    #[ORM\Column(name: "isLocked")]
    private bool $isLocked = false; // CHANGEMENT: non-null, valeur par defaut

    #[ORM\Column(name: "updatedAt", nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false)]
    private ?User $author = null;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post')]
    private Collection $comments;

    /** @var Collection<int, Reaction> */
    #[ORM\OneToMany(targetEntity: Reaction::class, mappedBy: 'post')]
    private Collection $reactions;

    /** @var Collection<int, Image> */
    #[ORM\OneToMany(targetEntity: Image::class, mappedBy: 'post')]
    private Collection $images;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->reactions = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    // ---------------- GETTERS / SETTERS ----------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getStatus(): string // CHANGEMENT: type de retour non-null
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isEdited(): bool // CHANGEMENT: type de retour non-null
    {
        return $this->isEdited;
    }

    public function setIsEdited(bool $isEdited): static
    {
        $this->isEdited = $isEdited;
        return $this;
    }

    public function isPinned(): bool // CHANGEMENT: type de retour non-null
    {
        return $this->isPinned;
    }

    public function setIsPinned(bool $isPinned): static
    {
        $this->isPinned = $isPinned;
        return $this;
    }

    public function isLocked(): bool // CHANGEMENT: type de retour non-null
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): static
    {
        $this->isLocked = $isLocked;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /** @return Collection<int, Reaction> */
    public function getReactions(): Collection
    {
        return $this->reactions;
    }

    /** @return Collection<int, Image> */
    public function getImages(): Collection
    {
        return $this->images;
    }
}


