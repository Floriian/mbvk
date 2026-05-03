<?php

namespace App\Service;

use App\Security\TokenUser;

final class MockLdapUserProvider implements LdapUserProviderInterface
{
    // Passwords are verified via sha256 to match the legacy-go behaviour.
    // The real production LDAP does a bind-and-search instead.
    private array $directory;

    public function __construct()
    {
        $this->directory = [
            'kovacs.janos' => [
                'pw_hash'      => hash('sha256', 'Kovacs123!'),
                'display_name' => 'Kovács János',
                'email'        => 'kovacs.janos@example.local',
                'roles'        => ['ROLE_USER'],
            ],
            'szabo.eva' => [
                'pw_hash'      => hash('sha256', 'Szabo456!'),
                'display_name' => 'Szabó Éva',
                'email'        => 'szabo.eva@example.local',
                'roles'        => ['ROLE_USER'],
            ],
            'admin' => [
                'pw_hash'      => hash('sha256', 'AdminPass789!'),
                'display_name' => 'Rendszergazda',
                'email'        => 'admin@example.local',
                'roles'        => ['ROLE_USER', 'ROLE_ADMIN'],
            ],
        ];
    }

    public function authenticate(string $usernamme, string $password): TokenUser
    {
        $entry = $this->directory[$usernamme] ?? null;
        if ($entry === null || !hash_equals($entry['pw_hash'], hash('sha256', $password))) {
            throw new \RuntimeException('invalid credentials');
        }

        return new TokenUser($usernamme, $entry['display_name'], $entry['email'], $entry['roles']);
    }
}
