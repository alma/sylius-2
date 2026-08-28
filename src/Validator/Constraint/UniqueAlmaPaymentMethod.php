<?php

declare(strict_types=1);

namespace Alma\Sylius\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Enforces the "only one Alma PaymentMethod per shop" invariant.
 *
 * Applied at PaymentMethod level by the Sylius admin validation groups
 * (registered via prepend in {@see \Alma\Sylius\DependencyInjection\AlmaSyliusExtension}).
 * See specs-sylius/module-configuration, Requirement "Only one Alma
 * PaymentMethod per shop".
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueAlmaPaymentMethod extends Constraint
{
    public string $message = 'alma_sylius.form.error.payment_method_already_exists';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
