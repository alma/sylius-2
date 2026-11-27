<?php

declare(strict_types=1);

namespace Alma\Sylius\BusinessEvents;

use Alma\Sylius\Checkout\PlanGroup;
use Alma\Sylius\Eligibility\AlmaEligibility;
use Alma\Sylius\Repository\AlmaBusinessEventRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\OrderInterface;

class CheckoutEligibilityRecorder
{
    public function __construct(
        private readonly AlmaBusinessEventRepository $repository,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array<string, AlmaEligibility> $eligibilities
     */
    public function record(OrderInterface $cart, array $eligibilities): void
    {
        $cartId = $cart->getId();
        if ($cartId === null) {
            return;
        }

        $wasBnplEligible = false;
        foreach ($eligibilities as $eligibility) {
            if ($eligibility->isEligible() && PlanGroup::classify($eligibility) !== PlanGroup::PayNow) {
                $wasBnplEligible = true;

                break;
            }
        }

        try {
            $this->repository->saveEligibility($cartId, $wasBnplEligible);
        } catch (\Throwable $exception) {
            $this->logger->warning('alma.business_events.eligibility_save_failed', [
                'cart_id' => $cartId,
                'exception' => $exception::class,
            ]);
        }
    }
}
