<?php

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Symfony\Component\Config\Definition\Processor;

class ConfigDefinitionTest extends \PHPUnit_Framework_TestCase
{
    private $config;

    private $dataDir = __DIR__ . '/../../../../tests/data';

    public function setUp()
    {
        $this->config = $this->getConfig();
    }

    public function testDefaultValues()
    {
        $parameters = $this->processConfiguration($this->config['parameters']);
        $this->assertEquals(10, $parameters['retriesCount']);
        $this->assertEquals(false, $parameters['nonConflictPrimaryKey']);
    }

    public function testRetriesCount()
    {
        $config = $this->config;
        $config['parameters']['retriesCount'] = 5;
        $parameters = $this->processConfiguration($config['parameters']);
        $this->assertEquals(5, $parameters['retriesCount']);
    }

    private function processConfiguration($config)
    {
        $processor = new Processor();
        return $processor->processConfiguration(
            new ConfigDefinition(),
            [$config]
        );
    }

    private function getConfig()
    {
        $config = json_decode(file_get_contents($this->dataDir . '/config.json'), true);
        $config['parameters']['data_dir'] = $this->dataDir;
        return $config;
    }
}
