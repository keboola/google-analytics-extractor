<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigDefinitionTest extends TestCase
{
    private array $config;

    private string $dataDir = __DIR__ . '/../../../../tests/data';

    public function setUp(): void
    {
        $this->config = $this->getConfig();
    }

    public function testDefaultValues(): void
    {
        unset($this->config['parameters']['retriesCount']);
        $config = new Config($this->config, new ConfigDefinition());
        Assert::assertEquals(8, $config->getRetries());
        Assert::assertEquals(false, $config->getNonConflictPrimaryKey());
    }

    public function testRetriesCount(): void
    {
        $config = $this->config;
        $config['parameters']['retriesCount'] = 5;
        $config = new Config($config, new ConfigDefinition());
        Assert::assertEquals(5, $config->getRetries());
    }

    public function testErrorProfilesPropertiesMustConfigured(): void
    {
        $config = $this->config;
        unset($config['parameters']['profiles']);
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Profiles or Properties must be configured.');
        new Config($config, new ConfigDefinition());
    }

    public function testDateRangeLastrunOldConfig(): void
    {
        $config = $this->getConfig('_old');

        $configData = new Config($config, new OldConfigDefinition());

        Assert::assertFalse($configData->hasLastRunState());
        Assert::assertEquals([], $configData->getLastRunState());
    }

    public function testDateRangeLastrun(): void
    {
        $config = $this->config;

        $config['parameters']['query']['dateRanges'] = [[
            'startDate' => Config::STATE_LAST_RUN_DATE,
            'endDate' => '-1 day',
        ]];

        $configData = new Config($config, new ConfigDefinition());

        Assert::assertIsArray($configData->getData());

        $config['parameters']['query']['dateRanges'][] = [
            'startDate' => Config::STATE_LAST_RUN_DATE,
            'endDate' => '-2 day',
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot set "lastrun" Date Range more than once.');
        new Config($config, new ConfigDefinition());
    }

    public function testAddSegmentDimension(): void
    {
        $config = $this->config;

        $config['parameters']['query']['segments'] = [[
            'segmentId' => '-1',
        ]];

        $configData = new Config($config, new ConfigDefinition());

        $dimensions = array_map(fn($v) => $v['name'], $configData->getParameters()['query']['dimensions']);

        $this->assertTrue(in_array('ga:segment', $dimensions));

        $config['parameters']['query']['dimensions'][] = ['name' => 'ga:segment'];

        $configData = new Config($config, new ConfigDefinition());

        $dimensions = array_filter(
            $configData->getParameters()['query']['dimensions'],
            fn($v) => $v['name'] === 'ga:segment',
        );

        $this->assertCount(1, $dimensions);
    }

    public function testKeepEmptyRowsDefaultAndCustom(): void
    {
        // Default value (not set in config)
        $config = $this->config;
        unset($config['parameters']['query']['keepEmptyRows']);
        $configData = new Config($config, new ConfigDefinition());
        $this->assertArrayHasKey('keepEmptyRows', $configData->getQuery());
        $this->assertTrue($configData->getQuery()['keepEmptyRows']);

        // Set to false
        $config['parameters']['query']['keepEmptyRows'] = false;
        $configData = new Config($config, new ConfigDefinition());
        $this->assertFalse($configData->getQuery()['keepEmptyRows']);
    }

    private function getConfig(string $suffix = ''): array
    {
        $config = json_decode((string) file_get_contents($this->dataDir . '/config' . $suffix . '.json'), true);
        return $config;
    }
}
