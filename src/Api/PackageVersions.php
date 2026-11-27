<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Composer\InstalledVersions;

final class PackageVersions
{
    public const PLUGIN_PACKAGE = 'alma/sylius-2-payment';
    public const SDK_PACKAGE = 'alma/alma-php-client';

    public static function plugin(): string
    {
        return self::of(self::PLUGIN_PACKAGE) ?? 'dev';
    }

    public static function of(string $package): ?string
    {
        if (!InstalledVersions::isInstalled($package)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion($package);
    }
}
