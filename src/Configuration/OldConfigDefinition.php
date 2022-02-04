<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class OldConfigDefinition extends BaseConfigDefinition
{
    public function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();

        $parametersNode
            ->ignoreExtraKeys()
            ->children()
                ->scalarNode('outputBucket')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('nonConflictPrimaryKey')
                    ->defaultValue(false)
                ->end()
                ->integerNode('retriesCount')
                    ->min(0)
                    ->defaultValue(8)
                ->end()
                ->arrayNode('profiles')->isRequired()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('id')->isRequired()->end()
                            ->scalarNode('name')->isRequired()->end()
                            ->scalarNode('webPropertyId')->isRequired()->end()
                            ->scalarNode('webPropertyName')->isRequired()->end()
                            ->scalarNode('accountId')->isRequired()->end()
                            ->scalarNode('accountName')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('queries')->isRequired()
                    ->prototype('array')
                        ->ignoreExtraKeys()
                        ->children()
                        ->enumNode('endpoint')->defaultValue('reports')->values(['mcf', 'reports'])->end()
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
                                ->scalarNode('filtersExpression')->defaultValue(null)->end()
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
                                            ->scalarNode('segmentId')->end()
                                        ->end()
                                    ->end()
                                ->end()
                                ->scalarNode('viewId')->defaultValue(null)->end()
                                ->arrayNode('dateRanges')
                                    ->prototype('array')
                                        ->children()
                                            ->scalarNode('startDate')->end()
                                            ->scalarNode('endDate')->end()
                                        ->end()
                                    ->end()
                                ->end()
                                ->integerNode('startIndex')->min(1)->end()
                                ->integerNode('maxResults')->min(100)->end()
                                ->scalarNode('samplingLevel')->end()
                            ->end()
                        ->end()
                        ->scalarNode('outputTable')->isRequired()->cannotBeEmpty()->end()
                        ->booleanNode('enabled')->defaultValue(true)->end()
                        ->enumNode('antisampling')->values([null, 'none', 'dailyWalk', 'adaptive'])->end()
                    ->end()
                ->end()
            ->end()
        ->end()
        ;

        return $parametersNode;
    }
}
