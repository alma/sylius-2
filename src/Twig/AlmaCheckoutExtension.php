<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Alma\Sylius\Checkout\FeePlanGrouper;
use Sylius\Component\Core\Model\OrderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AlmaCheckoutExtension extends AbstractExtension
{
    public function __construct(
        private readonly AlmaCheckoutEligibilityProvider $provider,
        private readonly FeePlanGrouper $grouper,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('alma_eligibility_groups', $this->almaEligibilityGroups(...)),
        ];
    }

    /**
     * @return list<\Alma\Sylius\Checkout\View\FeePlanGroupView>
     */
    public function almaEligibilityGroups(OrderInterface $order): array
    {
        return $this->grouper->group($this->provider->getEligibilities($order));
    }
}
