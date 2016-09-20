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
        $rootNode = $treeBuilder->root('parameters');

        $rootNode
            ->children()
                ->scalarNode('outputBucket')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('data_dir')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
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
                                        ->prototype('array')
                                            ->children()
                                                ->scalarNode('expression')->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('dimensions')
                                        ->prototype('array')
                                            ->children()
                                                ->scalarNode('name')->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->scalarNode('filtersExpression')
                                        ->defaultValue(null)
                                    ->end()
                                    ->arrayNode('orderBys')
                                        ->prototype('array')
                                            ->children()
                                                ->scalarNode('fieldName')->end()
                                                ->scalarNode('orderType')->end()
                                                ->scalarNode('sortOrder')->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('segments')
                                        ->prototype('array')
                                            ->children()
                                                ->scalarNode('segmentId')
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->scalarNode('viewId')
                                        ->defaultValue(null)
                                    ->end()
                                    ->arrayNode('dateRanges')
                                        ->prototype('array')
                                            ->children()
                                                ->scalarNode('startDate')
                                                ->end()
                                                ->scalarNode('endDate')
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
                            ->enumNode('antisampling')
                                ->values(array(null, 'none', 'dailyWalk', 'adaptive'))
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
