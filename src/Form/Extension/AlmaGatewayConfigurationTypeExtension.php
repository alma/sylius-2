<?php

declare(strict_types=1);

namespace Alma\Sylius\Form\Extension;

use Alma\Sylius\Form\Type\AlmaGatewayConfigurationType;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonAutocompleteType;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Adds the excluded taxons selector, persisted as taxon codes — the pattern
 * Sylius uses for its promotion taxon rules (cf. specs-sylius/category-exclusion).
 */
final class AlmaGatewayConfigurationTypeExtension extends AbstractTypeExtension
{
    /**
     * @param DataTransformerInterface<TaxonInterface, string|null> $taxonsToCodesTransformer
     */
    public function __construct(
        private readonly DataTransformerInterface $taxonsToCodesTransformer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('excluded_taxon_codes', TaxonAutocompleteType::class, [
                'label' => 'alma_sylius.form.configuration.exclusion.taxons',
                'multiple' => true,
                'required' => false,
            ])
            ->get('excluded_taxon_codes')->addModelTransformer($this->taxonsToCodesTransformer)
        ;
    }

    public static function getExtendedTypes(): iterable
    {
        return [AlmaGatewayConfigurationType::class];
    }
}
