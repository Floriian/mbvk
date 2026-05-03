<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAuctionRequestDto
{
    #[Assert\Choice([
        'pending',
        'active',
        'closed',
    ])]
    public ?string $status = null;
}
