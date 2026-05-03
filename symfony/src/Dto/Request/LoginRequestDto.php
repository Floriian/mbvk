<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

class LoginRequestDto
{
    #[Assert\NotBlank]
    public string $username = '';

    #[Assert\NotBlank]
    public string $password = '';
}
