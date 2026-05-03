<?php

namespace App\Dto\Response;

class GetAssetResponseDto {
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public string $min_price,
        public string $category
    ){}
}
