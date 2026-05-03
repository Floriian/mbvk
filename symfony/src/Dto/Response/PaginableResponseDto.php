<?php

namespace App\Dto\Response;

class PaginableResponseDto {
    public function __construct(
        public array $items
    ) {
    }
}
