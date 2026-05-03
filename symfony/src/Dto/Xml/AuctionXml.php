<?php

namespace App\Dto\Xml;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

class AuctionXml
{
    #[Assert\NotBlank]
    #[SerializedName('case_no')]
    public string $caseNo;

    #[Assert\NotBlank]
    public string $debtor;

    #[Assert\NotBlank]
    #[SerializedName('starts_at')]
    public string $startsAt;

    /** @var AuctionAssetXml[] */
    #[Assert\Valid]
    public array $assets;

    public static function fromXml(string $xml): self
    {
        $parsed = simplexml_load_string($xml);
        $dto = new self();
        $dto->caseNo   = trim((string)$parsed->case_no);
        $dto->debtor   = trim((string)$parsed->debtor);
        $dto->startsAt = trim((string)$parsed->starts_at);
        $dto->assets   = [];

        if(empty($parsed->assets)) {
            throw new \InvalidArgumentException("Az aukciónak legalább egy vagyontárggyal kell rendelkeznie.");
        }


        foreach ($parsed->assets->asset ?? [] as $asset) {
            $assetDto = new AuctionAssetXml();
            $assetDto->title       = trim((string)$asset->title);
            $assetDto->description = trim((string)$asset->description) ?: null;
            $assetDto->category    = (string)($asset->attributes()['category'] ?? '') ?: null;
            $assetDto->minPriceRaw = trim((string)$asset->min_price);
            $dto->assets[] = $assetDto;
        }

        return $dto;
    }
}
