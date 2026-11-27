<?php

declare(strict_types=1);

namespace Alma\Sylius\Form\Type;

use Alma\Sylius\Payment\AlmaPaymentDetailsKeys;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Alma\Sylius\Twig\AlmaCheckoutEligibilityProvider;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Choices are populated for every PaymentType (not only Alma) so the form tree
 * is complete at first paint; the Twig hook scoped to gateway factory `alma`
 * is what prevents the radios from leaking into other methods' UI.
 */
final class AlmaPaymentDetailsType extends AbstractType
{
    public function __construct(
        private readonly AlmaCheckoutEligibilityProvider $eligibilityProvider,
        private readonly AlmaPaymentMethodResolver $paymentMethodResolver,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $parent = $form->getParent();
            $payment = $parent?->getData();
            if (!$payment instanceof PaymentInterface) {
                return;
            }

            $order = $payment->getOrder();
            if ($order === null) {
                return;
            }

            if ($this->paymentMethodResolver->getConfiguration($order->getChannel())->isUnifiedPaymentButtonEnabled()) {
                return;
            }

            // Spec "Radio input label is the human-readable plan label": the visible label
            // is the variant description ("Paiement en 3×", "Paiement à J+15", …) returned
            // by AlmaEligibility::getLabel(). The submitted value stays the raw functional
            // plan key — downstream code (capture, IPN, refund) depends on it.
            $choices = [];
            $labels = [];
            foreach ($this->eligibilityProvider->getEligibilities($order) as $planKey => $eligibility) {
                if (!$eligibility->isEligible()) {
                    continue;
                }
                $choices[] = $planKey;
                $labels[$planKey] = $eligibility->getLabel();
            }

            // Spec "No forced default": no radio is pre-checked, except when paying
            // again a placed order, which keeps the plan of its last attempt if
            // still eligible.
            $data = $event->getData();
            if (is_array($data) && array_key_exists(AlmaPaymentDetailsKeys::FEE_PLAN_KEY, $data)) {
                $payingAgain = $order->getCheckoutState() === OrderCheckoutStates::STATE_COMPLETED;
                if (!$payingAgain || !\in_array($data[AlmaPaymentDetailsKeys::FEE_PLAN_KEY], $choices, true)) {
                    unset($data[AlmaPaymentDetailsKeys::FEE_PLAN_KEY]);
                    $event->setData($data);
                }
            }

            $form->add(AlmaPaymentDetailsKeys::FEE_PLAN_KEY, ChoiceType::class, [
                'choices' => $choices,
                'choice_label' => static fn (string $planKey): string => $labels[$planKey] ?? $planKey,
                'expanded' => true,
                'multiple' => false,
                'label' => false,
                'required' => false,
            ]);
        });
    }
}
