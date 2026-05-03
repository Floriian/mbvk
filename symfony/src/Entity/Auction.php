<?php

namespace App\Entity;

use App\Repository\AuctionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuctionRepository::class)]
#[ORM\Table(name: 'auctions')]
class Auction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, unique: true)]
    private string $caseNo;

    #[ORM\Column(type: Types::TEXT)]
    private string $debtor;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'starts_at')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: Types::TEXT, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at', options: ['default' => 'now()'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: Asset::class, mappedBy: 'auction', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $assets;

    public function __construct()
    {
        $this->assets    = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCaseNo(): string { return $this->caseNo; }
    public function setCaseNo(string $caseNo): static { $this->caseNo = $caseNo; return $this; }

    public function getDebtor(): string { return $this->debtor; }
    public function setDebtor(string $debtor): static { $this->debtor = $debtor; return $this; }

    public function getStartsAt(): \DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(\DateTimeImmutable $startsAt): static { $this->startsAt = $startsAt; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, Asset> */
    public function getAssets(): Collection { return $this->assets; }

    public function addAsset(Asset $asset): static
    {
        if (!$this->assets->contains($asset)) {
            $this->assets->add($asset);
            $asset->setAuction($this);
        }
        return $this;
    }

    public function removeAsset(Asset $asset): static
    {
        $this->assets->removeElement($asset);
        return $this;
    }
}
