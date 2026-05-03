<?php

namespace App\Dto\Response;

use DateTime;

class GetAuctionsResponseDto
{
    public function __construct(
        public int $id,
        public string $case_no,
        public string $debtor,
        public string $starts_at,
        public string $status,
        public int $asset_count
    ){}
}
