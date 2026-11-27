<?php

declare(strict_types=1);

namespace Alma\Sylius\Refund;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Alma refunds before the Refund Plugin's refund command commits: if that
 * commit fails, Alma has refunded a refund Sylius does not record. Messenger
 * handles a DispatchAfterCurrentBusStamp message only once the command has
 * committed, so a refund still unconfirmed when the request ends was lost by
 * Sylius, and is logged.
 */
class AlmaRefundCommitWatcher
{
    /** @var array<int, array{payment_id: mixed, order_number: string, amount: int}> */
    private array $awaitingRecord = [];

    private int $nextRefundKey = 0;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function expect(PaymentInterface $almaPayment, string $orderNumber, int $amount): void
    {
        $refundKey = $this->nextRefundKey++;
        $this->awaitingRecord[$refundKey] = [
            'payment_id' => $almaPayment->getId(),
            'order_number' => $orderNumber,
            'amount' => $amount,
        ];

        $this->commandBus->dispatch(new Envelope(new AlmaRefundRecorded($refundKey), [new DispatchAfterCurrentBusStamp()]));
    }

    public function __invoke(AlmaRefundRecorded $recorded): void
    {
        unset($this->awaitingRecord[$recorded->refundKey]);
    }

    public function reportUnrecorded(): void
    {
        foreach ($this->awaitingRecord as $unrecordedRefund) {
            $this->logger->error('alma.refund.not_recorded', $unrecordedRefund);
        }
        $this->awaitingRecord = [];
    }
}
