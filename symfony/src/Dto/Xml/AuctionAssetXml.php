<?php

namespace App\Dto\Xml;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

class AuctionAssetXml
{
    #[Assert\NotBlank]
    public string $title;

    public ?string $description;

    #[SerializedName('min_price')]
    public string $minPriceRaw;

    public ?string $category;
}
