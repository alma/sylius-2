<?php

declare(strict_types=1);

namespace Alma\Sylius\Form\Extension;

use Alma\Sylius\Form\Type\AlmaPaymentDetailsType;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\PaymentType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

final class AlmaPaymentTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('details', AlmaPaymentDetailsType::class, [
            'label' => false,
        ]);
    }

    public static function getExtendedTypes(): array
    {
        return [PaymentType::class];
    }
}
