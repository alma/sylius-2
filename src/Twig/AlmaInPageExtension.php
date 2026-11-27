<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Alma\Sylius\InPage\InPageModal;
use Alma\Sylius\InPage\InPageSettingsProvider;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AlmaInPageExtension extends AbstractExtension
{
    public function __construct(
        private readonly InPageSettingsProvider $settingsProvider,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('alma_in_page_script_url', $this->scriptUrl(...)),
            new TwigFunction('alma_in_page_frame_settings', $this->settingsProvider->frameSettings(...)),
            new TwigFunction('alma_in_page_plan_settings', InPageSettingsProvider::planSettings(...)),
            new TwigFunction('alma_in_page_modal_settings', $this->modalSettings(...)),
        ];
    }

    public function scriptUrl(): string
    {
        return InPageSettingsProvider::SCRIPT_URL;
    }

    /**
     * @return array<string, int|string>|null
     */
    public function modalSettings(OrderInterface $order): ?array
    {
        $almaPaymentId = $this->requestStack->getMainRequest()?->query->get(InPageModal::QUERY_PARAMETER);

        return $this->settingsProvider->modalSettings($order, \is_string($almaPaymentId) ? $almaPaymentId : null);
    }
}
