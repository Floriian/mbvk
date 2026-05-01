<?php

namespace App\Service;

use App\Security\TokenUser;

interface LdapUserProviderInterface
{
    /** @throws \RuntimeException on invalid credentials */
    public function authenticate(string $username, string $password): TokenUser;
}
