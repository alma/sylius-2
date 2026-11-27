<?php

declare(strict_types=1);

namespace Alma\Sylius\Refund;

use Psr\Log\LoggerInterface;
use Sylius\RefundPlugin\Command\SendCreditMemo;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The refund is already recorded: a credit memo that cannot be emailed is
 * only logged, and the merchant resends it from the Refund Plugin's credit
 * memo list.
 */
final class SendCreditMemoAfterRefundHandler
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendCreditMemoAfterRefund $sendCreditMemo): void
    {
        try {
            $this->commandBus->dispatch(new SendCreditMemo($sendCreditMemo->creditMemoNumber));
        } catch (\Throwable $sendFailure) {
            $cause = $sendFailure instanceof HandlerFailedException ? ($sendFailure->getPrevious() ?? $sendFailure) : $sendFailure;
            $this->logger->error('alma.refund.credit_memo_not_sent', [
                'credit_memo_number' => $sendCreditMemo->creditMemoNumber,
                'error' => $cause::class,
            ]);
        }
    }
}
