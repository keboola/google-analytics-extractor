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
                ->arrayNode('image_parameters')
                ->end()
                ->append($this->addAuthorizationNode())
                ->scalarNode('data_dir')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('parameters')
                    ->children()
                        ->arrayNode('profiles')
                            ->isRequired()
                            ->prototype('array')
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
                                    ->arrayNode('query')
                                        ->children()
                                            ->arrayNode('metrics')
                                                ->prototype('scalar')->end()
                                            ->end()
                                            ->arrayNode('dimensions')
                                                ->prototype('scalar')->end()
                                            ->end()
                                            ->arrayNode('filters')
                                                ->prototype('scalar')->end()
                                            ->end()
                                            ->arrayNode('segments')
                                                ->prototype('scalar')->end()
                                            ->end()
                                            ->scalarNode('viewId')
                                                ->defaultValue(null)
                                            ->end()
                                            ->arrayNode('dateRanges')
                                                ->prototype('array')
                                                    ->children()
                                                        ->scalarNode('since')
                                                        ->end()
                                                        ->scalarNode('until')
                                                        ->end()
                                                    ->end()
                                                ->end()
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

    public function addAuthorizationNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('authorization');

        $node
            ->children()
                ->arrayNode('oauth_api')
                    ->children()
                        ->arrayNode('credentials')
                            ->children()
                                ->scalarNode('appKey')
                                ->end()
                                ->scalarNode('#appSecret')
                                ->end()
                                ->arrayNode('#data')
                                    ->children()
                                        ->scalarNode('access_token')
                                        ->end()
                                        ->scalarNode('refresh_token')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
