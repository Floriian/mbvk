<?php

namespace App\Dto\Response;

class GetAuctionResponseDto {
    public function __construct(
        public int $id,
        public string $status,
        public string $case_no,
        public string $debtor,
        public string $starts_at,
        public array $assets
    ) {  }
}
