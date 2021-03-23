<?php

namespace RL;

use InvalidArgumentException;
use RL\Exception\AccessDeniedException;
use RL\Repository\ApiKeyRepository;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;

class Authenticator implements SimplePreAuthenticatorInterface
{
    private ApiKeyRepository $apiKeyRepository;

    public function __construct(ApiKeyRepository $apiKeyRepository) {
        $this->apiKeyRepository = $apiKeyRepository;
    }

    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        try {
            $method = $token->getAttribute('method');
            $route  = $token->getAttribute('route');
        } catch (InvalidArgumentException $exception) {
            return false;
        }

        if ($tokenRecord = $this->validateTokenRouteAndMethod($token->getCredentials(), $route, $method)) {
            $token->setUser($tokenRecord->getApp());

            return $token;
        } else {
            throw new AccessDeniedException(403, 'Access Denied.');
        }
    }

    public function validateTokenRouteAndMethod($token, $route, $method)
    {
        if ($token = $this->apiKeyRepository->findByToken($token)) {
            $accesses = $token->getApiKeyAccesses();

            foreach ($accesses as $access) {
                if (fnmatch(
                        $access->getRoute(),
                        $route
                    ) && (strtoupper($access->getMethod()) === 'ANY' || strcasecmp(
                            $method,
                            $access->getMethod()
                        ) == 0)) {
                    return $token;
                }
            }
        }

        return null;
    }

    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return $token instanceof PreAuthenticatedToken && $token->getProviderKey() === $providerKey;
    }
}
