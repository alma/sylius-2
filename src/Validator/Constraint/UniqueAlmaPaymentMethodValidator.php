<?php

declare(strict_types=1);

namespace Alma\Sylius\Validator\Constraint;

use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueAlmaPaymentMethodValidator extends ConstraintValidator
{
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueAlmaPaymentMethod) {
            throw new UnexpectedTypeException($constraint, UniqueAlmaPaymentMethod::class);
        }

        if ($value === null) {
            return;
        }

        if (!$value instanceof PaymentMethodInterface) {
            throw new UnexpectedValueException($value, PaymentMethodInterface::class);
        }

        if (!$this->isAlma($value)) {
            return;
        }

        $takenChannelNames = [];
        foreach ($this->otherAlmaPaymentMethods($value) as $other) {
            foreach ($value->getChannels() as $channel) {
                if ($other->hasChannel($channel)) {
                    $takenChannelNames[$channel->getCode()] = $channel->getName() ?? $channel->getCode();
                }
            }
        }

        if ($takenChannelNames === []) {
            return;
        }

        // Sylius' admin channels field renders no error of its own: a form-level error is shown at the top.
        $this->context->buildViolation($constraint->message)
            ->setParameter('%channels%', implode(', ', $takenChannelNames))
            ->addViolation();
    }

    /**
     * @return iterable<PaymentMethodInterface>
     */
    private function otherAlmaPaymentMethods(PaymentMethodInterface $paymentMethod): iterable
    {
        foreach ($this->paymentMethodRepository->findAll() as $existing) {
            if (!$existing instanceof PaymentMethodInterface || $existing === $paymentMethod || !$this->isAlma($existing)) {
                continue;
            }

            if ($existing->getId() !== null && $existing->getId() === $paymentMethod->getId()) {
                continue;
            }

            yield $existing;
        }
    }

    private function isAlma(PaymentMethodInterface $paymentMethod): bool
    {
        return $paymentMethod->getGatewayConfig()?->getFactoryName() === AlmaPaymentMethodResolver::GATEWAY_FACTORY_NAME;
    }
}
