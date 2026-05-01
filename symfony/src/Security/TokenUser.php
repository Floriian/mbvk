<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class TokenUser implements UserInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $displayName,
        private readonly string $email,
        private readonly array $roles,
    ) {}

    public function getUserIdentifier(): string { return $this->username; }
    public function getDisplayName(): string { return $this->displayName; }
    public function getEmail(): string { return $this->email; }
    public function getRoles(): array { return $this->roles; }
    public function eraseCredentials(): void {}

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'display_name' => $this->displayName,
            'email' => $this->email,
            'roles' => $this->roles,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['username'],
            $data['display_name'],
            $data['email'],
            $data['roles'],
        );
    }
}
