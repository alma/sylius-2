<?php

declare(strict_types=1);

namespace Alma\Sylius\Refund;

use Sylius\RefundPlugin\Event\CreditMemoGenerated;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Replaces the Refund Plugin's listener, which emails the credit memo inside
 * the refund command's transaction. Messenger handles a
 * DispatchAfterCurrentBusStamp message only once that command has committed:
 * a refund rolled back emails nothing, and a failing email cannot roll back a
 * refund Alma made.
 */
final class SendCreditMemoAfterCommitListener
{
    public function __construct(private readonly MessageBusInterface $commandBus)
    {
    }

    public function __invoke(CreditMemoGenerated $creditMemoGenerated): void
    {
        $this->commandBus->dispatch(new Envelope(
            new SendCreditMemoAfterRefund($creditMemoGenerated->number()),
            [new DispatchAfterCurrentBusStamp()],
        ));
    }
}
