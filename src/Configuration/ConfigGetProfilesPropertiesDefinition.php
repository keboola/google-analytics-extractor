<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\Configuration\Node\ServiceAccountNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigGetProfilesPropertiesDefinition extends BaseConfigDefinition
{
    public function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();

        $parametersNode->children()->append(new ServiceAccountNode())->end();

        return $parametersNode;
    }
}
