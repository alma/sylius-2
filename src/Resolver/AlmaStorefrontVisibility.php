<?php

declare(strict_types=1);

namespace Alma\Sylius\Resolver;

use Alma\Sylius\Entity\AlmaConfiguration;
use Alma\Sylius\Security\BackOfficeUserDetector;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;

class AlmaStorefrontVisibility
{
    public function __construct(
        private readonly AlmaPaymentMethodResolver $almaResolver,
        private readonly BackOfficeUserDetector $backOfficeUserDetector,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    public function isHidden(): bool
    {
        $channel = $this->currentChannel();
        if ($channel === null || $this->almaResolver->getAlmaPaymentMethod($channel)?->isEnabled() !== true) {
            return true;
        }

        return $this->almaResolver->getConfiguration($channel)->getApiMode() === AlmaConfiguration::MODE_TEST
            && !$this->backOfficeUserDetector->isLoggedIn();
    }

    private function currentChannel(): ?ChannelInterface
    {
        try {
            return $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }
    }
}
