<?php

declare(strict_types=1);

namespace Alma\Sylius\EventListener;

use Alma\Sylius\CmsDataExport\ExportUrlDeclarer;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final class CmsDataExportDeclarationListener
{
    public function __construct(
        private readonly ExportUrlDeclarer $exportUrlDeclarer,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $paymentMethod = $event->getSubject();
        if ($paymentMethod instanceof PaymentMethodInterface) {
            $this->exportUrlDeclarer->declare($paymentMethod);
        }
    }
}
