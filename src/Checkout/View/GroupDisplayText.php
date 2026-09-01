<?php

declare(strict_types=1);

namespace Alma\Sylius\Checkout\View;

final class GroupDisplayText
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
    ) {
    }
}
