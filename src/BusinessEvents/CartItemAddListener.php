<?php

declare(strict_types=1);

namespace Alma\Sylius\BusinessEvents;

use Alma\Sylius\Dispatch\AfterCommitDispatcher;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Alma\Sylius\Resolver\AlmaStorefrontVisibility;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final class CartItemAddListener
{
    public function __construct(
        private readonly AfterCommitDispatcher $dispatcher,
        private readonly BusinessEventsSender $sender,
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly AlmaStorefrontVisibility $storefrontVisibility,
    ) {
    }

    public function onCartItemAdd(GenericEvent $event): void
    {
        $command = $event->getSubject();
        if (!$command instanceof AddToCartCommandInterface) {
            return;
        }

        $cart = $command->getCart();
        if (!$cart instanceof OrderInterface || $this->configurationResolver->getAlmaPaymentMethod($cart->getChannel()) === null) {
            return;
        }

        if ($this->storefrontVisibility->isHidden()) {
            return;
        }

        $this->dispatcher->record(fn () => $this->sender->sendCartInitiated($cart));
    }
}
