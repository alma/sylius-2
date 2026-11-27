<?php

declare(strict_types=1);

namespace Alma\Sylius;

use Alma\Sylius\DependencyInjection\Compiler\RefundPluginSupportedGatewaysPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AlmaSyliusPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RefundPluginSupportedGatewaysPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
