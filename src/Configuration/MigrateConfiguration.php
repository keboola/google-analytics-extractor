<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;

class MigrateConfiguration
{
    private Client $client;

    private string $configurationId;

    private string $componentId;

    public function __construct(string $configurationId)
    {
        $this->client = $this->getClient();
        $this->configurationId = $configurationId;
        $this->componentId = (string) getenv('KBC_COMPONENTID');
    }

    public function migrate(): void
    {
        $components = new Components($this->client);

        $configuration = $components->getConfiguration($this->componentId, $this->configurationId);

        $configuration = $configuration['configuration'];

        $queries = $configuration['parameters']['queries'];

        unset($configuration['parameters']['queries']);

        $componentConfiguration = new Configuration();
        $componentConfiguration
            ->setComponentId($this->componentId)
            ->setConfigurationId($this->configurationId)
            ->setChangeDescription('Migrate configuration to configRow.')
            ->setConfiguration($configuration)
        ;

        foreach ($queries as $query) {
            $queryName = $query['name'];
            $enabled = $query['enabled'];
            unset($query['name'], $query['enabled']);

            $row = new ConfigurationRow($componentConfiguration);
            $row
                ->setName($queryName)
                ->setConfiguration(['parameters' => $query])
                ->setIsDisabled(!$enabled)
            ;
            $components->addConfigurationRow($row);
        }

        $components->updateConfiguration($componentConfiguration);
    }

    private function getClient(): Client
    {
        return new Client([
            'url' => getenv('KBC_URL'),
            'token' => getenv('KBC_TOKEN'),
        ]);
    }
}
