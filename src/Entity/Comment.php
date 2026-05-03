<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "commentid")]
    private ?int $id = null;

    #[ORM\Column(name: "content", length: 1000)]
    private ?string $content = null;

    #[ORM\Column(name: "status", length: 100)]
    private string $status = 'PUBLISHED';

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: "createdAt", type: "datetime_immutable")]
    /** @phpstan-ignore-next-line */
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: "isEdited")]
    private bool $isEdited = false;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: "updatedAt", type: "datetime_immutable", nullable: true)]
    /** @phpstan-ignore-next-line */
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(name: "postid", referencedColumnName: "postId", nullable: false)]
    private ?Post $post = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(name: "userid", referencedColumnName: "id", nullable: false)]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(name: "parentCommentId", referencedColumnName: "commentid", nullable: true)]
    private ?self $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $replies;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getContent(): ?string { return $this->content; }

    public function setContent(string $content): static
    {
        $this->content = $content;
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

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function getPost(): ?Post { return $this->post; }

    public function setPost(?Post $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function getAuthor(): ?User { return $this->author; }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getParent(): ?self { return $this->parent; }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function getReplies(): Collection { return $this->replies; }
}