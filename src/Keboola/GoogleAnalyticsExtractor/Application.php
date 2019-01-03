<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 06/04/16
 * Time: 15:45
 */

namespace Keboola\GoogleAnalyticsExtractor;

use GuzzleHttp\Exception\RequestException;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use Keboola\GoogleAnalyticsExtractor\Exception\UserException;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Keboola\GoogleAnalyticsExtractor\Logger\KbcInfoProcessor;
use Keboola\GoogleAnalyticsExtractor\Logger\Logger;
use Monolog\Handler\NullHandler;
use Pimple\Container;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class Application
{
    private $container;

    public function __construct($config)
    {
        $container = new Container();
        $container['action'] = isset($config['action'])?$config['action']:'run';
        $container['parameters'] = $this->validateParamteters($config['parameters']);
        $container['logger'] = function ($c) use ($config) {
            $logger = new Logger($config['app_name']);
            $logger->pushProcessor(new KbcInfoProcessor());
            if ($c['action'] !== 'run') {
                $logger->setHandlers([new NullHandler(Logger::INFO)]);
            }
            return $logger;
        };
        if (empty($config['authorization'])) {
            throw new UserException('Missing authorization data');
        }
        $tokenData = json_decode($config['authorization']['oauth_api']['credentials']['#data'], true);
        $container['google_client'] = function ($c) use ($config, $tokenData) {
            $client = new RestApi(
                $config['authorization']['oauth_api']['credentials']['appKey'],
                $config['authorization']['oauth_api']['credentials']['#appSecret'],
                $tokenData['access_token'],
                $tokenData['refresh_token'],
                $c['logger']
            );
            $retries = ($c['action'] === 'run') ? $c['parameters']['retriesCount'] : 2;
            $client->setBackoffsCount($retries);
            return $client;
        };
        $container['google_analytics_client'] = function ($c) {
            return new Client($c['google_client'], $c['logger']);
        };
        $container['output'] = function ($c) {
            $options = [];
            if (isset($c['parameters']['nonConflictPrimaryKey'])) {
                $options['nonConflictPrimaryKey'] = boolval($c['parameters']['nonConflictPrimaryKey']);
            }
            return new Output($c['parameters']['data_dir'], $c['parameters']['outputBucket'], $options);
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
        $actionMethod = $this->container['action'] . 'Action';

        if (!method_exists($this, $actionMethod)) {
            throw new UserException(sprintf("Action '%s' does not exist.", $this['action']));
        }

        try {
            return $this->$actionMethod();
        } catch (RequestException $e) {
            $this->container['logger']->debug("Request failed", [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'response' => [
                    'statusCode' => $e->getResponse()->getStatusCode(),
                    'reasonPhrase' => $e->getResponse()->getReasonPhrase(),
                    'body' => $e->getResponse()->getBody()
                ]
            ]);
            if ($e->getCode() == 400) {
                throw new UserException($e->getMessage());
            }
            if ($e->getCode() == 401) {
                throw new UserException("Expired or wrong credentials, please reauthorize.", 401, $e);
            }
            if ($e->getCode() == 403) {
                if (strtolower($e->getResponse()->getReasonPhrase()) == 'forbidden') {
                    $this->container['logger']->warning("You don't have access to Google Analytics resource. Probably you don't have access to profile, or profile doesn't exists anymore.");
                    return [];
                } else {
                    throw new UserException("Reason: " . $e->getResponse()->getReasonPhrase(), 403, $e);
                }
            }
            if ($e->getCode() == 503) {
                throw new UserException("Google API error: " . $e->getMessage(), 503, $e);
            }
            if ($e->getCode() == 429) {
                throw new UserException($e->getMessage());
            }
            throw new ApplicationException($e->getResponse()->getBody(), 500, $e);
        }
    }

    private function runAction()
    {
        $extracted = [];
        $profiles = $this->container['parameters']['profiles'];
        $queries = array_filter($this->container['parameters']['queries'], function ($query) {
            return $query['enabled'];
        });

        /** @var Output $output */
        $output = $this->container['output'];
        $csv = $output->createCsvFile('profiles');
        $output->createManifest('profiles.csv', 'profiles', ['id'], true);
        $output->writeProfiles($csv, $profiles);

        /** @var Extractor $extractor */
        $extractor = $this->container['extractor'];
        $extracted[] = $extractor->run($queries, $profiles);

        return [
            'status' => 'ok',
            'extracted' => $extracted
        ];
    }

    private function sampleAction()
    {
        if (empty($this->container['parameters']['profiles'])) {
            throw new UserException("No profiles configured. You have to register at least one profile.");
        }
        $profile = $this->container['parameters']['profiles'][0];
        $query = $this->container['parameters']['queries'][0];

        if (empty($query['query']['viewId'])) {
            $query['query']['viewId'] = (string) $profile['id'];
        }

        /** @var Extractor $extractor */
        $extractor = $this->container['extractor'];
        return $extractor->getSampleReport($query);
    }

    private function sampleJsonAction()
    {
        if (empty($this->container['parameters']['profiles'])) {
            throw new UserException("No profiles configured. You have to register at least one profile.");
        }
        $profile = $this->container['parameters']['profiles'][0];
        $query = $this->container['parameters']['queries'][0];

        if (empty($query['query']['viewId'])) {
            $query['query']['viewId'] = (string) $profile['id'];
        }

        /** @var Extractor $extractor */
        $extractor = $this->container['extractor'];
        return $extractor->getSampleReportJson($query);
    }

    private function segmentsAction()
    {
        /** @var Extractor $extractor */
        $extractor = $this->container['extractor'];
        return $extractor->getSegments();
    }

    private function customMetricsAction()
    {
        $profile = $this->container['parameters']['profiles'][0];

        /** @var Extractor $extractor */
        $extractor = $this->container['extractor'];
        return $extractor->getCustomMetrics($profile['accountId'], $profile['webPropertyId']);
    }

    private function validateParamteters($parameters)
    {
        // no parameters needed for `segments` action
        if ($this->container['action'] == 'segments') {
            return [];
        }

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
