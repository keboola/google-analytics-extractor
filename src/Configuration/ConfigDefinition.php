<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigDefinition extends BaseConfigDefinition
{
    public function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();

        $parametersNode
            ->validate()
                ->always(function ($item) {
                    if (empty($item['profiles']) && empty($item['properties'])) {
                        throw new InvalidConfigurationException('Profiles or Properties must be configured.');
                    }
                    if (!empty($item['query']['dateRanges'])) {
                        $filteredDateRanges = array_filter(
                            $item['query']['dateRanges'],
                            fn($v) => $v['startDate'] === Config::STATE_LAST_RUN_DATE
                        );
                        if (count($filteredDateRanges) > 1) {
                            throw new InvalidConfigurationException('Cannot set "lastrun" Date Range more than once.');
                        }
                    }
                    return $item;
                })
            ->end()
            ->ignoreExtraKeys(true)
            ->children()
                ->scalarNode('outputBucket')->isRequired()->cannotBeEmpty()->end()
                ->integerNode('nonConflictPrimaryKey')
                    ->defaultValue(false)
                ->end()
                ->integerNode('retriesCount')
                    ->min(0)
                    ->defaultValue(8)
                ->end()
                ->arrayNode('profiles')
                    ->prototype('array')
                        ->ignoreExtraKeys()
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
                ->arrayNode('properties')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('accountKey')
                                ->isRequired()
                            ->end()
                            ->scalarNode('accountName')
                                ->isRequired()
                            ->end()
                            ->scalarNode('propertyKey')
                                ->isRequired()
                            ->end()
                            ->scalarNode('propertyName')
                                ->isRequired()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->enumNode('endpoint')
                    ->defaultValue('reports')
                    ->values(['mcf', 'reports', 'data-api'])
                ->end()
                ->arrayNode('query')
                    ->children()
                        ->arrayNode('metrics')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('name')->end()
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
                        ->integerNode('startIndex')
                            ->min(1)
                        ->end()
                        ->integerNode('maxResults')
                            ->min(100)
                        ->end()
                        ->scalarNode('samplingLevel')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('outputTable')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->enumNode('antisampling')
                    ->values([null, 'none', 'dailyWalk', 'adaptive'])
                ->end()
            ->end()
        ->end()
        ;

        return $parametersNode;
    }
}
