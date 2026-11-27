<?php

declare(strict_types=1);

namespace Alma\Sylius\CmsDataExport;

use Alma\Client\Application\DTO\MerchantData\CmsInfoDto;
use Alma\Sylius\Api\PackageVersions;
use Composer\InstalledVersions;
use Sylius\Bundle\CoreBundle\SyliusCoreBundle;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class CmsInfoProvider
{
    private const SYLIUS_PLUGIN_PACKAGE_TYPE = 'sylius-plugin';

    public function provide(PaymentMethodInterface $paymentMethod): CmsInfoDto
    {
        $themeName = AlmaChannels::primaryOf($paymentMethod)?->getThemeName();

        return new CmsInfoDto([
            'cms_name' => 'Sylius',
            'cms_version' => SyliusCoreBundle::VERSION,
            'third_parties_plugins' => $this->thirdPartyPlugins(),
            'theme_name' => $themeName,
            'theme_version' => $themeName !== null ? PackageVersions::of($themeName) : null,
            'language_name' => 'PHP',
            'language_version' => \PHP_VERSION,
            'alma_plugin_version' => PackageVersions::plugin(),
            'alma_sdk_name' => PackageVersions::SDK_PACKAGE,
            'alma_sdk_version' => PackageVersions::of(PackageVersions::SDK_PACKAGE),
        ]);
    }

    /**
     * @return list<array{name: string, version: string|null}>|null
     */
    private function thirdPartyPlugins(): ?array
    {
        $plugins = [];
        foreach (array_unique(InstalledVersions::getInstalledPackagesByType(self::SYLIUS_PLUGIN_PACKAGE_TYPE)) as $package) {
            if ($package === PackageVersions::PLUGIN_PACKAGE) {
                continue;
            }
            $plugins[] = ['name' => $package, 'version' => PackageVersions::of($package)];
        }

        return $plugins === [] ? null : $plugins;
    }
}
