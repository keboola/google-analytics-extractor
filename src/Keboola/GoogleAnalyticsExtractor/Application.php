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
        $container['parameters'] = $this->validateParamteters($config['parameters']);
        $container['logger'] = function () {
            return new Logger(APP_NAME);
        };
        $container['google_client'] = function () use ($config) {
            return new RestApi(
                $config['authorization']['oauth_api']['credentials']['appKey'],
                $config['authorization']['oauth_api']['credentials']['#appSecret'],
                $config['authorization']['oauth_api']['credentials']['#data']['access_token'],
                $config['authorization']['oauth_api']['credentials']['#data']['refresh_token']
            );
        };
        $container['google_analytics_client'] = function ($c) {
            return new Client($c['google_client']);
        };
        $container['output'] = function ($c) {
            return new Output($c['parameters']['data_dir']);
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
        $profiles = $this->container['parameters']['profiles'];
        $queries = array_filter($this->container['parameters']['queries'], function ($query) {
            return $query['enabled'];
        });

        /** @var Output $output */
        $output = $this->container['output'];
        $csv = $output->createCsvFile('profiles');
        $output->createManifest('profiles', 'id', true);
        $output->writeProfiles($csv, $profiles);

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

    private function validateParamteters($parameters)
    {
        try {
            $processor = new Processor();
            return $processor->processConfiguration(
                new ConfigDefinition(),
                [$parameters]
            );
        } catch (InvalidConfigurationException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        }
    }
}
