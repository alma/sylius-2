<?php

declare(strict_types=1);

namespace Alma\Sylius\Exclusion;

use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves the exclusion message from the merchant-edited `exclusion_messages`
 * config (cf. specs-sylius/category-exclusion), for both the product page and
 * the configuration pre-fill.
 *
 * Same fallback chain as the checkout group texts: exact locale → persisted
 * value of the store's English locale → the catalog default wording (English
 * when the locale is not covered by the catalog).
 */
final class ExclusionMessageResolver
{
    public const DEFAULT_MESSAGE_KEY = 'alma_sylius.exclusion.default_message';

    private const FALLBACK_LOCALE = 'en';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $messages
     */
    public function resolve(array $messages, string $locale): string
    {
        $message = $this->nonEmptyString($messages[$locale] ?? null) ?? $this->englishMessage($messages);
        if ($message !== null) {
            return $message;
        }

        $catalogLocale = $this->isCovered($locale) ? $locale : self::FALLBACK_LOCALE;

        return $this->translator->trans(self::DEFAULT_MESSAGE_KEY, [], null, $catalogLocale);
    }

    /**
     * @param array<string, mixed> $messages
     */
    private function englishMessage(array $messages): ?string
    {
        foreach ($messages as $locale => $message) {
            if (str_starts_with(strtolower((string) $locale), self::FALLBACK_LOCALE)) {
                $englishMessage = $this->nonEmptyString($message);
                if ($englishMessage !== null) {
                    return $englishMessage;
                }
            }
        }

        return null;
    }

    private function isCovered(string $locale): bool
    {
        return $this->translator instanceof TranslatorBagInterface
            && $this->translator->getCatalogue($locale)->has(self::DEFAULT_MESSAGE_KEY);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
