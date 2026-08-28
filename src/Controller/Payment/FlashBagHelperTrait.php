<?php

declare(strict_types=1);

namespace Alma\Sylius\Controller\Payment;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Adds a Sylius-compatible flash message on the main request's session, if any.
 *
 * Used by the customer-return and cancel routes. Both are hit from the
 * customer's browser via a redirect from Alma's hosted page, so a session
 * exists and a flash is the natural way to surface a non-blocking
 * acknowledgement. The IPN controller does NOT use this — it is hit by
 * Alma's servers and returns JSON.
 *
 * Implementations MUST inject a {@see RequestStack} as `$this->requestStack`.
 */
trait FlashBagHelperTrait
{
    private function addFlash(string $type, string $flashKey): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return;
        }
        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add($type, $flashKey);
        }
    }
}
