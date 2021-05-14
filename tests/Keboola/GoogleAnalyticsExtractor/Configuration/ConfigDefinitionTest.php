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

    public function testErrorSetProfilesPropertiesTogether(): void
    {
        $config = $this->config;
        $config['parameters']['properties'] = [[
            'accountKey' => 'accounts/123456',
            'accountName' => 'Keboola',
            'propertyKey' => 'properties/123456',
            'propertyName' => 'users',
        ]];
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Both profiles and properties cannot be set together.');
        new Config($config, new ConfigDefinition());
    }

    private function getConfig(): array
    {
        $config = json_decode((string) file_get_contents($this->dataDir . '/config.json'), true);
        return $config;
    }
}
