<?php

declare(strict_types=1);

namespace Alma\Sylius\EventListener;

use Alma\Sylius\Security\BackOfficeUserDetector;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * A page rendered with Alma for a back-office admin in Test mode must not
 * reach other visitors through a shared cache (cf. specs-sylius/module-configuration).
 * Symfony's session listener usually marks it private already, but a shop can
 * turn that off.
 */
final class BackOfficeUserPrivateResponseListener
{
    public function __construct(private readonly BackOfficeUserDetector $backOfficeUserDetector)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && $this->backOfficeUserDetector->hasDetectedLogin()) {
            $event->getResponse()->setPrivate();
        }
    }
}
