<?php

declare(strict_types=1);

namespace Alma\Sylius\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('alma_sylius');
        $treeBuilder->getRootNode()
            ->children()
                // Context of the back-office firewall, whose login the shop reads in Test mode.
                ->scalarNode('admin_firewall_context')->defaultValue('admin')->cannotBeEmpty()->end()
            ->end();

        return $treeBuilder;
    }
}
