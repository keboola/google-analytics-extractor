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
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Monolog\Logger;
use Pimple\Container;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class Application
{
    private $container;

    public function __construct($config)
    {
        $container = new Container();
        $container['config'] = $this->validateConfig($config);
        $container['logger'] = function () {
            return new Logger(APP_NAME);
        };
        $container['google_client'] = function ($c) {
            return new RestApi(
                $c['config']['authorization']['oauth_api']['credentials']['appKey'],
                $c['config']['authorization']['oauth_api']['credentials']['#appSecret'],
                $c['config']['authorization']['oauth_api']['credentials']['#data']['access_token'],
                $c['config']['authorization']['oauth_api']['credentials']['#data']['refresh_token']
            );
        };
        $container['google_analytics_client'] = function ($c) {
            return new Client($c['google_client']);
        };
        $container['output'] = function ($c) {
            return new Output($c['config']['data_dir']);
        };
        $container['extractor'] = function ($c) {
            return new Extractor(
                $c['google_analytics_client'],
                $c['output'],
                $c['logger']
            );
        };

        $this->container = $container;
    }

    public function run()
    {
        $extracted = [];
        $profiles = $this->container['config']['parameters']['profiles'];
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
                new ConfigDefinition(),
                [$config]
            );
        } catch (InvalidConfigurationException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        }
    }
}
