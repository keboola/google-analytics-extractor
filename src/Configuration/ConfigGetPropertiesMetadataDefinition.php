<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigGetPropertiesMetadataDefinition extends BaseConfigDefinition
{
    public function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();

        $parametersNode
            ->children()
                ->arrayNode('properties')
                    ->arrayPrototype()
                    ->children()
                        ->scalarNode('accountKey')->isRequired()->end()
                        ->scalarNode('accountName')->isRequired()->end()
                        ->scalarNode('propertyKey')->isRequired()->end()
                        ->scalarNode('propertyName')->isRequired()->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $parametersNode;
    }
}
