<?php

namespace App\Service;

use App\Security\TokenUser;
use Predis\Client;

final class RedisClient
{
    private const TOKEN_PREFIX  = 'auth:token:';
    private const TOKEN_TTL     = 8 * 3600; // match legacy-go
    private const LOGIN_LOG_KEY = 'auth:events:recent';
    private const LOGIN_LOG_MAX = 100;

    private Client $redis;

    public function __construct(string $redisDsn)
    {
        $this->redis = new Client($redisDsn);
    }

    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function storeToken(string $token, TokenUser $user): void
    {
        $this->redis->setex(
            self::TOKEN_PREFIX . $token,
            self::TOKEN_TTL,
            json_encode($user->toArray(), \JSON_THROW_ON_ERROR),
        );
    }

    public function lookupToken(string $token): ?TokenUser
    {
        $raw = $this->redis->get(self::TOKEN_PREFIX . $token);
        if ($raw === null) {
            return null;
        }

        try {
            return TokenUser::fromArray(json_decode($raw, true, 512, \JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            return null;
        }
    }

    public function logLoginEvent(
        string $username,
        bool $success,
        string $ip,
        string $userAgent,
        ?string $reason = null,
    ): void {
        $event = [
            'timestamp'  => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::RFC3339),
            'username'   => $username,
            'success'    => $success,
            'ip'         => $ip,
            'user_agent' => $userAgent,
        ];
        if ($reason !== null) {
            $event['reason'] = $reason;
        }

        try {
            $json = json_encode($event, \JSON_THROW_ON_ERROR);
            $this->redis->pipeline(function ($pipe) use ($json): void {
                $pipe->lpush(self::LOGIN_LOG_KEY, [$json]);
                $pipe->ltrim(self::LOGIN_LOG_KEY, 0, self::LOGIN_LOG_MAX - 1);
            });
        } catch (\Exception) {
            // best-effort — a Redis failure must not block the login response
        }
    }

    public function recentLoginEvents(int $limit): array
    {
        $raw    = $this->redis->lrange(self::LOGIN_LOG_KEY, 0, min($limit, self::LOGIN_LOG_MAX) - 1);
        $events = [];
        foreach ((array) $raw as $item) {
            try {
                $events[] = json_decode($item, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                // skip corrupt entries
            }
        }

        return $events;
    }
}
