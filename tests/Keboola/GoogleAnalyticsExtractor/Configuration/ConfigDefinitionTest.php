<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

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

    private function getConfig(): array
    {
        $config = json_decode((string) file_get_contents($this->dataDir . '/config.json'), true);
        return $config;
    }
}
