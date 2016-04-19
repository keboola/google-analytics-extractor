<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 06/04/16
 * Time: 15:50
 */
namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConfigDefinition implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('config');

        $rootNode
            ->children()
                ->scalarNode('data_dir')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('parameters')
                    ->children()
                        ->arrayNode('profiles')
                            ->children()
                                ->scalarNode('id')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('name')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('webPropertyId')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('webPropertyName')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('accountId')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('accountName')
                                    ->isRequired()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('queries')
                            ->isRequired()
                            ->prototype('array')
                                ->children()
                                    ->integerNode('id')
                                        ->isRequired()
                                        ->min(0)
                                    ->end()
                                    ->scalarNode('name')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->arrayNode('queries')
                                        ->children()
                                            ->arrayNode('metrics')
                                                ->isRequired()
                                                ->cannotBeEmpty()
                                            ->end()
                                            ->arrayNode('dimensions')
                                                ->isRequired()
                                                ->cannotBeEmpty()
                                            ->end()
                                            ->arrayNode('filters')
                                            ->end()
                                            ->scalarNode('viewId')
                                                ->defaultValue(null)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->scalarNode('outputTable')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->booleanNode('enabled')
                                        ->defaultValue(true)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('image_parameters')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
