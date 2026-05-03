<?php

namespace App\Dto\Request;
use Symfony\Component\Validator\Constraints as Assert;


class GetAuctionsRequestDto {
    #[Assert\Choice([
        'pending',
        'active',
        'closed',
    ])]
    public ?string $status = null;

    public ?string $case_no = null;

    public int $page = 1;
    public int $limit = 10;
}
