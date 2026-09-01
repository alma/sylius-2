<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Alma\Sylius\Checkout\FeePlanGrouper;
use Alma\Sylius\Checkout\GroupDisplayTextsResolver;
use Alma\Sylius\Checkout\PlanGroup;
use Alma\Sylius\Checkout\View\GroupDisplayText;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AlmaCheckoutExtension extends AbstractExtension
{
    public function __construct(
        private readonly AlmaCheckoutEligibilityProvider $provider,
        private readonly FeePlanGrouper $grouper,
        private readonly GroupDisplayTextsResolver $displayTextsResolver,
        private readonly AlmaPaymentMethodResolver $paymentMethodResolver,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('alma_eligibility_groups', $this->almaEligibilityGroups(...)),
            new TwigFunction('alma_group_display', $this->almaGroupDisplay(...)),
        ];
    }

    /**
     * @return list<\Alma\Sylius\Checkout\View\FeePlanGroupView>
     */
    public function almaEligibilityGroups(OrderInterface $order): array
    {
        return $this->grouper->group($this->provider->getEligibilities($order));
    }

    public function almaGroupDisplay(PlanGroup $group): GroupDisplayText
    {
        return $this->displayTextsResolver->resolve(
            $this->paymentMethodResolver->getConfiguration()->getDisplayTexts() ?? [],
            $group,
            $this->localeContext->getLocaleCode(),
        );
    }
}
