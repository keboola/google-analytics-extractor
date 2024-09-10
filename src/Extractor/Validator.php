<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Generator;
use GuzzleHttp\Exception\ClientException;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Psr\Log\LoggerInterface;

class Validator
{
    private Client $gaApi;

    private LoggerInterface $logger;

    public function __construct(Client $gaApi, LoggerInterface $logger)
    {
        $this->gaApi = $gaApi;
        $this->logger = $logger;
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
            } else {
                $this->logger->warning(
                    sprintf('Cannot access to property "%s".', $configProperty['propertyName']),
                );
            }
        }
    }

    public function validateProfiles(array $configProfiles): Generator
    {
        try {
            $allowProfiles = $this->gaApi->getAccountProfiles();
        } catch (ClientException $e) {
            $this->logger->warning(
                sprintf(
                    'Cannot download list of profiles. Skip validation profiles. Reason: "%s".',
                    $e->getMessage(),
                ),
            );
            array_walk($configProfiles, function ($profile) {
                yield $profile;
            });
            return;
        }

        $listAllowProfiles = (array) array_combine(
            array_map(fn($v) => $v['id'], $allowProfiles),
            array_map(fn($v) => $v['name'], $allowProfiles),
        );

        foreach ($configProfiles as $configProfile) {
            if (array_key_exists($configProfile['id'], $listAllowProfiles)) {
                yield $configProfile;
            } else {
                $this->logger->warning(
                    sprintf('Cannot access to profile "%s".', $configProfile['name']),
                );
            }
        }
    }
}
