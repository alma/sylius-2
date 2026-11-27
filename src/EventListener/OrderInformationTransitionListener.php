<?php

declare(strict_types=1);

namespace Alma\Sylius\EventListener;

use Alma\Sylius\OrderInformation\OrderInformationDispatcher;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

final class OrderInformationTransitionListener
{
    private const ORDER_TRANSITIONS = ['cancel', 'fulfill'];
    /** `cancel` only follows the order's own cancellation, which is already sent. */
    private const ORDER_SHIPPING_TRANSITIONS = ['partially_ship', 'ship'];

    public function __construct(
        private readonly OrderInformationDispatcher $dispatcher,
    ) {
    }

    public function onOrderTransition(CompletedEvent $event): void
    {
        $this->recordStatus($event, self::ORDER_TRANSITIONS);
    }

    public function onOrderShippingTransition(CompletedEvent $event): void
    {
        $this->recordStatus($event, self::ORDER_SHIPPING_TRANSITIONS);
    }

    public function onShipmentShipped(CompletedEvent $event): void
    {
        $shipment = $event->getSubject();
        if ($shipment instanceof ShipmentInterface) {
            $this->dispatcher->recordShipment($shipment);
        }
    }

    /**
     * @param list<string> $reportedTransitions
     */
    private function recordStatus(CompletedEvent $event, array $reportedTransitions): void
    {
        $order = $event->getSubject();
        $transition = $event->getTransition();
        if (!$order instanceof OrderInterface
            || $transition === null
            || !\in_array($transition->getName(), $reportedTransitions, true)
        ) {
            return;
        }

        $this->dispatcher->recordStatus($order, $transition->getTos()[0]);
    }
}
