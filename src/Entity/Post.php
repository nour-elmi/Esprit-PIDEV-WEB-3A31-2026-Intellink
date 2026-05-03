<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "postId")]
    private ?int $id = null;

    #[ORM\Column(name: "content", length: 1000)]
    private ?string $content = null;

    #[Gedmo\Slug(fields: ['content'], updatable: false)]
    #[ORM\Column(name: "slug", length: 255, unique: true, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(name: "status", length: 100)]
    private string $status = 'PUBLISHED';

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: "createdAt", type: "datetime_immutable")]
    /** @phpstan-ignore-next-line */
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: "isEdited")]
    private bool $isEdited = false;

    #[ORM\Column(name: "isPinned")]
    private bool $isPinned = false;

    #[ORM\Column(name: "isLocked")]
    private bool $isLocked = false;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: "updatedAt", type: "datetime_immutable", nullable: true)]
    /** @phpstan-ignore-next-line */
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(name: "userid", referencedColumnName: "id", nullable: false)]
    private ?User $author = null;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post')]
    private Collection $comments;

    #[ORM\OneToMany(targetEntity: Reaction::class, mappedBy: 'post')]
    private Collection $reactions;

    #[ORM\OneToMany(targetEntity: Image::class, mappedBy: 'post')]
    private Collection $images;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->reactions = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getContent(): ?string { return $this->content; }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getSlug(): ?string { return $this->slug; }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getStatus(): string { return $this->status; }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isEdited(): bool { return $this->isEdited; }

    public function setIsEdited(bool $isEdited): static
    {
        $this->isEdited = $isEdited;
        return $this;
    }

    public function isPinned(): bool { return $this->isPinned; }

    public function setIsPinned(bool $isPinned): static
    {
        $this->isPinned = $isPinned;
        return $this;
    }

    public function isLocked(): bool { return $this->isLocked; }

    public function setIsLocked(bool $isLocked): static
    {
        $this->isLocked = $isLocked;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function getAuthor(): ?User { return $this->author; }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getComments(): Collection { return $this->comments; }

    public function getReactions(): Collection { return $this->reactions; }

    public function getImages(): Collection { return $this->images; }
}