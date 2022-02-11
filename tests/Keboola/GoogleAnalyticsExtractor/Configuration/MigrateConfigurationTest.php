<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class MigrateConfigurationTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = new Client([
            'url' => (string) getenv('KBC_URL'),
            'token' => (string) getenv('KBC_TOKEN'),
        ]);

        parent::setUp();
    }

    public function testMigrate(): void
    {
        $configurationArray = json_decode($this->getConfig(), true);

        $components = new Components($this->client);

        $configuration = new Configuration();
        $configuration
            ->setName('testMigrationConfig')
            ->setComponentId('keboola.ex-google-analytics-v4')
            ->setConfiguration($configurationArray)
        ;
        $savedConfig = $components->addConfiguration($configuration);

        $migrate = new MigrateConfiguration($savedConfig['id']);
        $migrate->migrate();

        $migratedConfiguration = $components->getConfiguration(
            'keboola.ex-google-analytics-v4',
            $savedConfig['id']
        );

        $query = $configurationArray['parameters']['queries'][0];
        unset($query['name'], $query['enabled']);

        Assert::assertEquals($query, $migratedConfiguration['rows'][0]['configuration']['parameters']);
        Assert::assertEquals(
            $configurationArray['parameters']['profiles'],
            $migratedConfiguration['configuration']['parameters']['profiles']
        );
        Assert::assertEquals(
            $configurationArray['parameters']['outputBucket'],
            $migratedConfiguration['configuration']['parameters']['outputBucket']
        );

        $components->deleteConfiguration(
            'keboola.ex-google-analytics-v4',
            $savedConfig['id']
        );
    }

    private function getConfig(): string
    {
        return <<<JSON
{
  "authorization": {
    "oauth_api": {
      "credentials": {
        "appKey": "appKey",
        "#appSecret": "appSecret",
        "#data": "data"
      }
    }
  },
  "parameters": {
    "profiles": [
      {
        "accountId": "testAccountId",
        "webPropertyId": "testWebPropertyId",
        "webPropertyName": "testWebPropertyName",
        "accountName": "testAccountName",
        "name": "testName",
        "id": "testId"
      }
    ],
    "outputBucket": "testOutputBucket",
    "queries": [
      {
        "name": "query1Test",
        "enabled": true,
        "outputTable": "table1",
        "endpoint": "reports",
        "query": {
          "dateRanges": [
            {
              "startDate": "midnight first day of this month",
              "endDate": "midnight last day of this month"
            }
          ],
          "metrics": [
            {
              "expression": "ga:sessions"
            },
            {
              "expression": "ga:pageviews"
            },
            {
              "expression": "ga:transactions"
            }
          ],
          "dimensions": [
            {
              "name": "ga:medium"
            },
            {
              "name": "ga:sourceMedium"
            },
            {
              "name": "ga:date"
            }
          ]
        }
      }
    ]
  }
}
JSON;
    }
}
