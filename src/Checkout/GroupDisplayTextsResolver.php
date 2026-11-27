<?php

declare(strict_types=1);

namespace Alma\Sylius\Checkout;

use Alma\Sylius\Checkout\View\GroupDisplayText;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves a payment group's displayed title and description from the
 * merchant-edited `display_texts` config (cf. specs-sylius/module-configuration),
 * for both the checkout rendering and the Display section pre-fill.
 *
 * Fallback chain (canonical checkout Requirement « Group titles and descriptions
 * come from the merchant configuration »): exact locale → persisted values of
 * the store's English locale → the plugin's default wording (translation
 * catalog, English when the locale is not covered by the catalog).
 */
final class GroupDisplayTextsResolver
{
    private const FALLBACK_LOCALE = 'en';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public const UNIFIED_PAYMENT_BUTTON = 'unified_payment_button';

    /**
     * @param array<string, array<string, array<string, mixed>>> $displayTexts
     */
    public function resolve(array $displayTexts, PlanGroup $group, string $locale): GroupDisplayText
    {
        return $this->resolveSlot($displayTexts, $group->value, $group->titleKey(), $group->subtitleKey(), $locale);
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $displayTexts
     */
    public function resolveUnifiedPaymentButton(array $displayTexts, string $locale): GroupDisplayText
    {
        return $this->resolveSlot(
            $displayTexts,
            self::UNIFIED_PAYMENT_BUTTON,
            'alma_sylius.checkout.unified_payment_button.title',
            'alma_sylius.checkout.unified_payment_button.subtitle',
            $locale,
        );
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $displayTexts
     */
    private function resolveSlot(array $displayTexts, string $slot, string $titleKey, string $subtitleKey, string $locale): GroupDisplayText
    {
        $entry = $displayTexts[$locale][$slot] ?? null;
        if (!\is_array($entry)) {
            $entry = $this->englishEntry($displayTexts, $slot);
        }

        if ($entry !== null) {
            $title = $this->nonEmptyString($entry['title'] ?? null);
            if ($title !== null) {
                return new GroupDisplayText($title, $this->nonEmptyString($entry['description'] ?? null));
            }
        }

        return new GroupDisplayText(
            $this->trans($titleKey, $locale),
            $this->defaultDescription($subtitleKey, $locale),
        );
    }

    /**
     * Which groups carry a default description is a catalog fact, not a code
     * fact (canonical checkout spec: default wording changes go through the
     * translation catalog): no subtitle entry in any catalog means no default.
     */
    private function defaultDescription(string $subtitleKey, string $locale): ?string
    {
        if (!$this->isCovered($subtitleKey, $locale) && !$this->isCovered($subtitleKey, self::FALLBACK_LOCALE)) {
            return null;
        }

        return $this->trans($subtitleKey, $locale);
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $displayTexts
     *
     * @return array<string, mixed>|null
     */
    private function englishEntry(array $displayTexts, string $slot): ?array
    {
        foreach ($displayTexts as $locale => $slots) {
            if (!str_starts_with(strtolower((string) $locale), self::FALLBACK_LOCALE)) {
                continue;
            }
            if (\is_array($slots[$slot] ?? null)) {
                return $slots[$slot];
            }
        }

        return null;
    }

    private function trans(string $key, string $locale): string
    {
        return $this->translator->trans($key, [], null, $this->isCovered($key, $locale) ? $locale : self::FALLBACK_LOCALE);
    }

    private function isCovered(string $key, string $locale): bool
    {
        return $this->translator instanceof TranslatorBagInterface
            && $this->translator->getCatalogue($locale)->has($key);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
