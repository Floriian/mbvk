<?php

namespace App\Controller;

use App\Dto\Request\LoginRequestDto;
use App\Security\TokenUser;
use App\Service\LdapUserProviderInterface;
use App\Service\RedisClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly LdapUserProviderInterface $ldap,
        private readonly RedisClient $redis,
    ) {}

    #[Route('/login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginRequestDto $dto, Request $request): JsonResponse
    {
        $ip        = $this->clientIp($request);
        $userAgent = $request->headers->get('User-Agent', '');

        try {
            $user = $this->ldap->authenticate(trim($dto->username), $dto->password);
        } catch (\RuntimeException $e) {
            $this->redis->logLoginEvent($dto->username, false, $ip, $userAgent, $e->getMessage());

            return $this->json(['error' => 'invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $this->redis->logLoginEvent($user->getUserIdentifier(), true, $ip, $userAgent);

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
