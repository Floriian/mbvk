<?php

namespace App\Controller;

use App\Security\TokenUser;
use App\Service\LdapUserProviderInterface;
use App\Service\RedisClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly LdapUserProviderInterface $ldap,
        private readonly RedisClient $redis,
    ) {}

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $body     = json_decode($request->getContent(), true) ?? [];
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if ($username === '' || $password === '') {
            return $this->json(['error' => 'username and password required'], Response::HTTP_BAD_REQUEST);
        }

        $ip        = $this->clientIp($request);
        $userAgent = $request->headers->get('User-Agent', '');

        try {
            $user = $this->ldap->authenticate($username, $password);
        } catch (\RuntimeException $e) {
            $this->redis->logLoginEvent($username, false, $ip, $userAgent, $e->getMessage());

            return $this->json(['error' => 'invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $this->redis->logLoginEvent($username, true, $ip, $userAgent);

        $token     = $this->redis->generateToken();
        $expiresAt = new \DateTimeImmutable('+8 hours', new \DateTimeZone('UTC'));
        $this->redis->storeToken($token, $user);

        return $this->json([
            'token'      => $token,
            'expires_at' => $expiresAt->format(\DateTimeInterface::RFC3339),
            'user'       => $user->toArray(),
        ]);
    }

    #[Route('/me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(#[CurrentUser] TokenUser $user): JsonResponse
    {
        return $this->json($user->toArray());
    }

    #[Route('/recent-logins', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function recentLogins(Request $request): JsonResponse
    {
        $limit  = max(1, min((int) $request->query->get('limit', 50), 100));
        $events = $this->redis->recentLoginEvents($limit);

        return $this->json(['events' => $events, 'count' => count($events)]);
    }

    private function clientIp(Request $request): string
    {
        $forwarded = $request->headers->get('X-Forwarded-For', '');
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }

        return $request->server->get('REMOTE_ADDR', '');
    }
}
