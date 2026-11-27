<?php

declare(strict_types=1);

namespace Alma\Sylius\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * A channel has at most one Alma PaymentMethod (cf. specs-sylius/module-configuration).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueAlmaPaymentMethod extends Constraint
{
    public string $message = 'alma_sylius.form.error.channel_already_has_alma_payment_method';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
