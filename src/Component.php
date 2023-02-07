<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor;

use DateTime;
use GuzzleHttp\Exception\RequestException;
use Keboola\Component\BaseComponent;
use Keboola\Component\Manifest\ManifestManager\Options\OutTableManifestOptions;
use Keboola\Component\UserException;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\Config;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigGetProfilesPropertiesDefinition;
use Keboola\GoogleAnalyticsExtractor\Configuration\MigrateConfiguration;
use Keboola\GoogleAnalyticsExtractor\Configuration\OldConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\Extractor\Validator;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;

class Component extends BaseComponent
{
    private const ACTION_SAMPLE = 'sample';
    private const ACTION_SAMPLE_JSON = 'sampleJson';
    private const ACTION_SEGMENTS = 'segments';
    private const ACTION_CUSTOM_METRICS = 'customMetrics';
    private const ACTION_GET_PROFILES_PROPERTIES = 'getProfilesProperties';

    public function getConfig(): Config
    {
        /** @var Config $config */
        $config = parent::getConfig();
        return $config;
    }

    protected function run(): void
    {
        if ($this->getConfigDefinitionClass() === OldConfigDefinition::class) {
            $migrateConfiguration = new MigrateConfiguration((string) getenv('KBC_CONFIGID'));
            $migrateConfiguration->migrate();
        }

        try {
            foreach ($this->getConfig()->getQueries($this->getConfigDefinitionClass()) as $query) {
                $this->runQuery($query);
            }
        } catch (RequestException $e) {
            $this->handleException($e);
        }

        if ($this->getConfigDefinitionClass() === ConfigDefinition::class && $this->getConfig()->hasLastRunState()) {
            $dateTime = new DateTime($this->getConfig()->getLastRunState()['endDate']);
            $this->writeOutputStateToFile([
                Config::STATE_LAST_RUN_DATE => $dateTime->format('Y-m-d'),
            ]);
        }
    }

    private function runQuery(array $query): void
    {
        $validator = new Validator(
            new Client($this->getGoogleRestApi(), $this->getLogger(), $this->getInputState()),
            $this->getLogger()
        );

        if ($this->getConfig()->processProfiles($this->getConfigDefinitionClass())) {
            // @TODO fixme
            //$validProfiles = $validator->validateProfiles($this->getConfig()->getProfiles());

            $this->getExtractor()->runProfiles(
                $query,
                $this->getConfig()->getProfiles()
            );

            $outTableManifestOptions = new OutTableManifestOptions();
            $outTableManifestOptions
                ->setDestination($this->getConfig()->getOutputBucket() . '.profiles')
                ->setIncremental(true)
                ->setPrimaryKeyColumns(['id']);

            $this->getManifestManager()->writeTableManifest('profiles.csv', $outTableManifestOptions);

            $output = new Output($this->getDataDir(), $this->getConfig()->getOutputBucket());
            $output->writeProfiles(
                $output->createCsvFile('profiles'),
                $this->getConfig()->getProfiles()
            );
        }

        if ($this->getConfig()->processProperties($this->getConfigDefinitionClass())) {
            $validProperties = $validator->validateProperties($this->getConfig()->getProperties());

            $this->getExtractor()->runProperties(
                $query,
                iterator_to_array($validProperties)
            );

            $outTableManifestOptions = new OutTableManifestOptions();
            $outTableManifestOptions
                ->setDestination($this->getConfig()->getOutputBucket() . '.properties')
                ->setIncremental(true)
                ->setPrimaryKeyColumns(['propertyKey']);

            $this->getManifestManager()->writeTableManifest('properties.csv', $outTableManifestOptions);

            $output = new Output($this->getDataDir(), $this->getConfig()->getOutputBucket());
            $output->writeProperties(
                $output->createCsvFile('properties'),
                $this->getConfig()->getProperties()
            );
        }
    }

    protected function runSampleAction(): array
    {
        $profile = $this->getConfig()->getProfiles()[0];
        $parameters = $this->getConfig()->getParameters();

        if ($this->getConfigDefinitionClass() === OldConfigDefinition::class) {
            $parameters += $parameters['queries'][0];
            unset($parameters['queries']);
        }

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

        if ($this->getConfigDefinitionClass() === OldConfigDefinition::class) {
            $parameters += $parameters['queries'][0];
            unset($parameters['queries']);
        }

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

    protected function getProfilesPropertiesAction(): array
    {
        return $this->getExtractor()->getProfilesPropertiesAction();
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }


    protected function getConfigDefinitionClass(): string
    {
        $action = $this->getRawConfig()['action'] ?? 'run';
        switch ($action) {
            case self::ACTION_GET_PROFILES_PROPERTIES:
                return ConfigGetProfilesPropertiesDefinition::class;
            default:
                $config = $this->getRawConfig();
                if (array_key_exists('queries', $config['parameters'])) {
                    return OldConfigDefinition::class;
                }
                return ConfigDefinition::class;
        }
    }

    protected function getSyncActions(): array
    {
        return [
            self::ACTION_GET_PROFILES_PROPERTIES => 'getProfilesPropertiesAction',
            self::ACTION_SAMPLE => 'runSampleAction',
            self::ACTION_SAMPLE_JSON => 'runSampleJsonAction',
            self::ACTION_SEGMENTS => 'runSegmentsAction',
            self::ACTION_CUSTOM_METRICS => 'runCustomMetricsAction',
        ];
    }

    private function getExtractor(): Extractor
    {
        return new Extractor(
            new Client($this->getGoogleRestApi(), $this->getLogger(), $this->getInputState()),
            new Output($this->getDataDir(), $this->getConfig()->getOutputBucket()),
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
                return;
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
