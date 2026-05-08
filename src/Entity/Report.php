<?php

namespace App\Entity;

use App\Repository\ReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id")]
    private int $id = 0; // CHANGEMENT: evite property.unusedType sur id Doctrine auto-genere

    #[ORM\Column(name: "type", length: 20, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: "targetId", nullable: true)]
    private ?int $targetId = null;

    #[ORM\Column(name: "reporterId", nullable: true)]
    private ?int $reporterId = null;

    #[ORM\Column(name: "reason", length: 200, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(name: "status", length: 20)]
    private string $status = 'OPEN'; // CHANGEMENT: non-null, valeur par defaut

    #[ORM\Column(name: "createdAt")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: "handledBy", nullable: true)]
    private ?int $handledBy = null;

    #[ORM\Column(name: "handledAt", nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'OPEN';
    }

    // ---------------- GETTERS / SETTERS ----------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTargetId(): ?int
    {
        return $this->targetId;
    }

    public function setTargetId(?int $targetId): static
    {
        $this->targetId = $targetId;
        return $this;
    }

    public function getReporterId(): ?int
    {
        return $this->reporterId;
    }

    public function setReporterId(?int $reporterId): static
    {
        $this->reporterId = $reporterId;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
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

    public function getHandledBy(): ?int
    {
        return $this->handledBy;
    }

    public function setHandledBy(?int $handledBy): static
    {
        $this->handledBy = $handledBy;
        return $this;
    }

    public function getHandledAt(): ?\DateTimeImmutable
    {
        return $this->handledAt;
    }

    public function setHandledAt(?\DateTimeImmutable $handledAt): static
    {
        $this->handledAt = $handledAt;
        return $this;
    }
}


