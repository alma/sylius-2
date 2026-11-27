<?php

declare(strict_types=1);

namespace Alma\Sylius\Exclusion;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A product is excluded when one of its product taxons, or an ancestor of one,
 * carries an excluded taxon code (cf. specs-sylius/category-exclusion).
 */
final class ExcludedTaxonMatcher
{
    /**
     * @param list<string> $excludedTaxonCodes
     */
    public function isProductExcluded(ProductInterface $product, array $excludedTaxonCodes): bool
    {
        if ($excludedTaxonCodes === []) {
            return false;
        }

        foreach ($product->getTaxons() as $taxon) {
            if (\in_array($taxon->getCode(), $excludedTaxonCodes, true)) {
                return true;
            }

            foreach ($taxon->getAncestors() as $ancestor) {
                if (\in_array($ancestor->getCode(), $excludedTaxonCodes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $excludedTaxonCodes
     */
    public function isOrderExcluded(OrderInterface $order, array $excludedTaxonCodes): bool
    {
        if ($excludedTaxonCodes === []) {
            return false;
        }

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if ($product !== null && $this->isProductExcluded($product, $excludedTaxonCodes)) {
                return true;
            }
        }

        return false;
    }
}
