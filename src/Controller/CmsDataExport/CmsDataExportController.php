<?php

declare(strict_types=1);

namespace Alma\Sylius\Controller\CmsDataExport;

use Alma\Client\Application\DTO\MerchantData\MerchantDataDto;
use Alma\Sylius\CmsDataExport\CmsFeaturesProvider;
use Alma\Sylius\CmsDataExport\CmsInfoProvider;
use Alma\Sylius\CmsDataExport\ExportUrlDeclarer;
use Alma\Sylius\Entity\AlmaConfiguration;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    '/alma/cms-data-export/{code}',
    name: ExportUrlDeclarer::EXPORT_ROUTE,
)]
final class CmsDataExportController
{
    private const SIGNATURE_HEADER = 'X-Alma-Signature';

    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly AlmaPaymentMethodResolver $almaPaymentMethodResolver,
        private readonly CmsInfoProvider $cmsInfoProvider,
        private readonly CmsFeaturesProvider $cmsFeaturesProvider,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(Request $request, string $code): Response
    {
        $response = $this->respond($request, $code);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function respond(Request $request, string $code): Response
    {
        if (!$request->isMethod(Request::METHOD_GET)) {
            return new Response('', Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $code]);
        if (!$paymentMethod instanceof PaymentMethodInterface || !$this->almaPaymentMethodResolver->isAlma($paymentMethod)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $configuration = $this->almaPaymentMethodResolver->getConfigurationOf($paymentMethod);
        if (!$this->isSignedByAlma($request, $configuration)) {
            $this->logger->warning('alma.cms_data_export.signature_invalid', ['payment_method_code' => $code]);

            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        $this->logger->info('alma.cms_data_export.served', ['payment_method_code' => $code]);

        return new JsonResponse((new MerchantDataDto())->toArray(
            $this->cmsInfoProvider->provide($paymentMethod),
            $this->cmsFeaturesProvider->provide($paymentMethod, $configuration),
        ));
    }

    private function isSignedByAlma(Request $request, AlmaConfiguration $configuration): bool
    {
        $liveKey = $configuration->getApiKeyLive();
        $merchantId = $configuration->getMerchantId();
        $signature = (string) $request->headers->get(self::SIGNATURE_HEADER, '');
        if ($liveKey === null || $merchantId === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $merchantId, $liveKey), $signature);
    }
}
