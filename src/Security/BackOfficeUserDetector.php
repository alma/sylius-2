<?php

declare(strict_types=1);

namespace Alma\Sylius\Security;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Service\ResetInterface;

class BackOfficeUserDetector implements ResetInterface
{
    private ?bool $loggedIn = null;

    /**
     * @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly FirewallMap $firewallMap,
        private readonly UserRepositoryInterface $adminUserRepository,
        private readonly string $adminFirewallContext,
    ) {
    }

    public function isLoggedIn(): bool
    {
        return $this->loggedIn ??= $this->detect();
    }

    /**
     * Whether this request already found an admin, without reading the session
     * when nothing asked (in Live mode, the session is never read).
     */
    public function hasDetectedLogin(): bool
    {
        return $this->loggedIn === true;
    }

    public function reset(): void
    {
        $this->loggedIn = null;
    }

    private function detect(): bool
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasPreviousSession()) {
            return false;
        }

        // A stateless firewall (the shop API) ignores the session even when the cookie is sent.
        if ($this->firewallMap->getFirewallConfig($request)?->isStateless() ?? true) {
            return false;
        }

        // The shop firewall only knows customers: the admin login lives under the
        // admin firewall's key of the shared session, where Symfony stores it serialized.
        $serializedToken = $request->getSession()->get('_security_' . $this->adminFirewallContext);
        if (!is_string($serializedToken)) {
            return false;
        }

        set_error_handler(static fn (int $type, string $message): never => throw new \ErrorException($message, 0, $type));
        try {
            $token = unserialize($serializedToken);
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }

        $user = $token instanceof TokenInterface ? $token->getUser() : null;
        if (!$user instanceof AdminUserInterface || $user->getId() === null) {
            return false;
        }

        $admin = $this->adminUserRepository->find($user->getId());

        return $admin instanceof AdminUserInterface
            && $admin->isEnabled()
            && in_array(AdminUserInterface::DEFAULT_ADMIN_ROLE, $admin->getRoles(), true)
            && !self::passwordChanged($user->getPassword(), $admin->getPassword());
    }

    /**
     * Same check as Symfony's ContextListener, which logs the admin out of the
     * back office once their password changed. Since Symfony 7.3 the session
     * only keeps a crc32c hash of the password.
     */
    private static function passwordChanged(?string $sessionPassword, ?string $currentPassword): bool
    {
        return $sessionPassword !== null
            && $sessionPassword !== $currentPassword
            && (strlen($sessionPassword) !== 8 || hash('crc32c', $currentPassword ?? $sessionPassword) !== $sessionPassword);
    }
}
