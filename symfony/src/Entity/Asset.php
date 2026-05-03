<?php

namespace App\Entity;

use App\Repository\AssetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ORM\Table(name: 'assets')]
class Asset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Auction::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(name: 'auction_id', nullable: false, onDelete: 'CASCADE')]
    private Auction $auction;

    #[ORM\Column(type: Types::TEXT)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BIGINT)]
    private int $minPrice;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getAuction(): Auction { return $this->auction; }
    public function setAuction(Auction $auction): static { $this->auction = $auction; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getMinPrice(): int { return $this->minPrice; }
    public function setMinPrice(int $minPrice): static { $this->minPrice = $minPrice; return $this; }

    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): static { $this->category = $category; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
