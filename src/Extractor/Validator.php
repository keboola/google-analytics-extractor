<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Generator;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;

class Validator
{
    private Client $gaApi;

    public function __construct(Client $gaApi)
    {
        $this->gaApi = $gaApi;
    }

    public function validateProperties(array $configProperties): Generator
    {
        $allowProperties = $this->gaApi->getAccountProperties();

        $listAllowProperties = [];
        foreach ($allowProperties as $allowProperty) {
            foreach ($allowProperty['propertySummaries'] as $propertySummary) {
                $listAllowProperties[$propertySummary['property']] = $propertySummary['displayName'];
            }
        }

        foreach ($configProperties as $configProperty) {
            if (array_key_exists($configProperty['propertyKey'], $listAllowProperties)) {
                yield $configProperty;
            }
        }
    }

    public function validateProfiles(array $configProfiles): Generator
    {
        $allowProfiles = $this->gaApi->getAccountProfiles();

        $listAllowProfiles = (array) array_combine(
            array_map(fn($v) => $v['id'], $allowProfiles),
            array_map(fn($v) => $v['name'], $allowProfiles)
        );

        foreach ($configProfiles as $configProfile) {
            if (array_key_exists($configProfile['id'], $listAllowProfiles)) {
                yield $configProfile;
            }
        }
    }
}
