<?php

declare(strict_types=1);

namespace Alma\Sylius\CmsDataExport;

use Alma\Client\Application\Exception\Endpoint\ConfigurationEndpointException;
use Alma\Sylius\Api\ConfigurationEndpointFactoryInterface;
use Alma\Sylius\Entity\AlmaConfiguration;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ExportUrlDeclarer
{
    public const EXPORT_ROUTE = 'alma_sylius_cms_data_export';

    public function __construct(
        private readonly AlmaPaymentMethodResolver $almaPaymentMethodResolver,
        private readonly ConfigurationEndpointFactoryInterface $configurationEndpointFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function declare(PaymentMethodInterface $paymentMethod): void
    {
        if (!$this->almaPaymentMethodResolver->isAlma($paymentMethod)) {
            return;
        }

        $liveKey = $this->almaPaymentMethodResolver->getConfigurationOf($paymentMethod)->getApiKeyLive();
        if ($liveKey === null) {
            return;
        }

        $exportUrl = $this->urlGenerator->generate(
            self::EXPORT_ROUTE,
            ['code' => $paymentMethod->getCode()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        try {
            $this->configurationEndpointFactory
                ->make($liveKey, AlmaConfiguration::MODE_LIVE)
                ->sendIntegrationsConfigurationsUrl($exportUrl);
        } catch (ConfigurationEndpointException $exception) {
            $this->logger->warning('alma.cms_data_export.url_declaration_failed', [
                'payment_method_code' => $paymentMethod->getCode(),
                'exception' => $exception::class,
            ]);

            return;
        }

        $this->logger->info('alma.cms_data_export.url_declared', [
            'payment_method_code' => $paymentMethod->getCode(),
        ]);
    }
}
