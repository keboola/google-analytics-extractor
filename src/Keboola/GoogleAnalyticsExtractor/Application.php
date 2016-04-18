<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 06/04/16
 * Time: 15:45
 */

namespace Keboola\GoogleAnalyticsExtractor;

use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\Exception\UserException;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Keboola\Temp\Temp;
use Monolog\Logger;
use Pimple\Container;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class Application
{
    private $configDefinition;

    private $container;

    public function __construct($config)
    {
        $container = new Container();
        $container['config'] = $this->validateConfig($config);
        $container['logger'] = function () {
            return new Logger(APP_NAME);
        };
        $container['temp'] = function () {
            return new Temp();
        };
        $container['google_client'] = function ($c) {
            new RestApi(
                $c['config']['authorization']['client_id'],
                $c['config']['authorization']['client_secret']
            );
        };
        $container['google_analytics_client'] = function ($c) {
            new Client($c['google_client']);
        };
        $container['extractor'] = function ($c) {
            return new Extractor(
                $c['config'],
                $c['google_analytics_client'],
                $c['logger']
            );
        };

        $this->container = $container;
        $this->configDefinition = new ConfigDefinition();
    }

    public function run()
    {
        $extracted = [];
        $profiles = $this->container['config']['parameters']['profiles'];

        //@todo: enabled
        $queries = array_filter($this->container['config']['parameters']['queries'], function ($query) {
            return $query['enabled'];
        });

        /** @var Extractor $extractor */
        $extractor = $this->container['extractor'];
        foreach ($profiles as $profile) {
            $extracted[] = $extractor->run($queries, $profile);
        }

        return [
            'status' => 'ok',
            'extracted' => $extracted
        ];
    }

    private function validateConfig($config)
    {
        try {
            $processor = new Processor();
            return $processor->processConfiguration(
                $this->configDefinition,
                [$config]
            );
        } catch (InvalidConfigurationException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        }
    }
}
