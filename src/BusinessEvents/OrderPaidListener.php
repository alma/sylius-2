<?php

declare(strict_types=1);

namespace Alma\Sylius\BusinessEvents;

use Alma\Sylius\Dispatch\AfterCommitDispatcher;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

final class OrderPaidListener
{
    public function __construct(
        private readonly AfterCommitDispatcher $dispatcher,
        private readonly BusinessEventsSender $sender,
    ) {
    }

    public function onOrderPaid(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->dispatcher->record(fn () => $this->sender->sendOrderConfirmed($order));
    }
}
