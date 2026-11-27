<?php

declare(strict_types=1);

namespace Alma\Sylius\InPage;

/**
 * The order page opens the In-Page modal for the Alma payment named by this
 * query parameter. Keeping it in the URL makes a reload reopen the same
 * payment; the page drops it once the customer closes the modal.
 */
final class InPageModal
{
    public const QUERY_PARAMETER = 'alma_in_page_payment';

    private function __construct()
    {
    }
}
