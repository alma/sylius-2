<?php

declare(strict_types=1);

namespace Alma\Sylius\OrderInformation;

use Alma\Sylius\Dispatch\AfterCommitDispatcher;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;

class OrderInformationDispatcher
{
    public function __construct(
        private readonly AfterCommitDispatcher $afterCommitDispatcher,
        private readonly OrderInformationSender $sender,
    ) {
    }

    public function recordStatus(OrderInterface $order, string $state): void
    {
        $this->afterCommitDispatcher->record(fn () => $this->sender->sendStatus($order, $state));
    }

    public function recordShipment(ShipmentInterface $shipment): void
    {
        $this->afterCommitDispatcher->record(fn () => $this->sender->sendShipment($shipment));
    }
}
