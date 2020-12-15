<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor;

use GuzzleHttp\Exception\RequestException;
use Keboola\Component\BaseComponent;
use Keboola\Component\Logger;
use Keboola\Component\Manifest\ManifestManager\Options\OutTableManifestOptions;
use Keboola\Component\UserException;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\Config;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigSegmentsDefinition;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;

class Component extends BaseComponent
{
    private const ACTION_SAMPLE = 'sample';
    private const ACTION_SAMPLE_JSON = 'sampleJson';
    private const ACTION_SEGMENTS = 'segments';
    private const ACTION_CUSTOM_METRICS = 'customMetrics';

    public function getConfig(): Config
    {
        /** @var Config $config */
        $config = parent::getConfig();
        return $config;
    }

    protected function run(): void
    {
        try {
            $this->getExtractor()->run(
                $this->getConfig()->getParameters(),
                $this->getConfig()->getProfiles()
            );

            $outTableManifestOptions = new OutTableManifestOptions();
            $outTableManifestOptions
                ->setIncremental(true)
                ->setPrimaryKeyColumns(['id']);

            $this->getManifestManager()->writeTableManifest('profiles', $outTableManifestOptions);

            $output = new Output($this->getDataDir());
            $output->writeProfiles(
                $output->createCsvFile('profiles'),
                $this->getConfig()->getProfiles()
            );
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }

    protected function runSampleAction(): array
    {
        $profile = $this->getConfig()->getProfiles()[0];
        $parameters = $this->getConfig()->getParameters();

        if (empty($parameters['query']['viewId'])) {
            $parameters['query']['viewId'] = (string) $profile['id'];
        }

        $result = [];
        try {
            $result = $this->getExtractor()->getSampleReport($parameters);
        } catch (RequestException $e) {
            $this->handleException($e);
        }

        return $result;
    }

    protected function runSampleJsonAction(): array
    {
        $profile = $this->getConfig()->getProfiles()[0];
        $parameters = $this->getConfig()->getParameters();

        if (empty($parameters['query']['viewId'])) {
            $parameters['query']['viewId'] = (string) $profile['id'];
        }

        $result = [];
        try {
            $result =  $this->getExtractor()->getSampleReportJson($parameters);
        } catch (RequestException $e) {
            $this->handleException($e);
        }

        return $result;
    }

    protected function runSegmentsAction(): array
    {
        $result = [];
        try {
            $result = $this->getExtractor()->getSegments();
        } catch (RequestException $e) {
            $this->handleException($e);
        }

        return $result;
    }

    protected function runCustomMetricsAction(): array
    {
        $profile = $this->getConfig()->getProfiles()[0];

        $result = [];
        try {
            $result = $this->getExtractor()->getCustomMetrics($profile['accountId'], $profile['webPropertyId']);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
        return $result;
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }


    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }

    protected function getSyncActions(): array
    {
        return [
            self::ACTION_SAMPLE => 'runSampleAction',
            self::ACTION_SAMPLE_JSON => 'runSampleJsonAction',
            self::ACTION_SEGMENTS => 'runSegmentsAction',
            self::ACTION_CUSTOM_METRICS => 'runCustomMetricsAction',
        ];
    }

    private function getExtractor(): Extractor
    {
        return new Extractor(
            new Client($this->getGoogleRestApi(), $this->getLogger()),
            new Output($this->getDataDir()),
            $this->getLogger()
        );
    }

    private function getGoogleRestApi(): RestApi
    {
        $tokenData = json_decode($this->getConfig()->getOAuthApiData(), true);

        $client = new RestApi(
            $this->getConfig()->getOAuthApiAppKey(),
            $this->getConfig()->getOAuthApiAppSecret(),
            $tokenData['access_token'],
            $tokenData['refresh_token'],
            $this->getLogger()
        );

        $client->setBackoffsCount($this->getConfig()->getRetries());
        return $client;
    }

    private function handleException(RequestException $e): void
    {
        if ($e->getResponse() === null) {
            throw new UserException($e->getMessage());
        }

        $this->getLogger()->debug('Request failed', [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'response' => [
                'statusCode' => $e->getResponse()->getStatusCode(),
                'reasonPhrase' => $e->getResponse()->getReasonPhrase(),
                'body' => $e->getResponse()->getBody(),
            ],
        ]);

        if ($e->getCode() === 400) {
            throw new UserException($e->getMessage());
        }

        if ($e->getCode() === 401) {
            throw new UserException('Expired or wrong credentials, please reauthorize.', 401, $e);
        }

        if ($e->getCode() === 403) {
            if (strtolower($e->getResponse()->getReasonPhrase()) === 'forbidden') {
                $this->getLogger()->warning(
                    'You don\'t have access to Google Analytics resource. ' .
                    'Probably you don\'t have access to profile, or profile doesn\'t exists anymore.'
                );
            } else {
                throw new UserException('Reason: ' . $e->getResponse()->getReasonPhrase(), 403, $e);
            }
        }

        if ($e->getCode() === 503) {
            throw new UserException('Google API error: ' . $e->getMessage(), 503, $e);
        }

        if ($e->getCode() === 429) {
            throw new UserException($e->getMessage());
        }

        throw new ApplicationException((string) $e->getResponse()->getBody(), 500, $e);
    }
}