<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor;

use DateTime;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Keboola\Component\BaseComponent;
use Keboola\Component\Manifest\ManifestManager\Options\OutTable\ManifestOptions;
use Keboola\Component\Manifest\ManifestManager\Options\OutTable\ManifestOptionsSchema;
use Keboola\Component\UserException;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\Config;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigGetProfilesPropertiesDefinition;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigGetPropertiesMetadataDefinition;
use Keboola\GoogleAnalyticsExtractor\Configuration\MigrateConfiguration;
use Keboola\GoogleAnalyticsExtractor\Configuration\OldConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\Extractor\Validator;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;

class Component extends BaseComponent
{
    private const string ACTION_SAMPLE = 'sample';
    private const string ACTION_SAMPLE_JSON = 'sampleJson';
    private const string ACTION_SEGMENTS = 'segments';
    private const string ACTION_CUSTOM_METRICS = 'customMetrics';
    private const string ACTION_GET_PROFILES_PROPERTIES = 'getProfilesProperties';
    private const string ACTION_GET_PROPERTIES_METADATA = 'getPropertiesMetadata';

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
            $this->getLogger(),
        );

        if ($this->getConfig()->processProfiles($this->getConfigDefinitionClass())) {
            // @TODO fixme
            //$validProfiles = $validator->validateProfiles($this->getConfig()->getProfiles());

            $this->getExtractor()->runProfiles(
                $query,
                $this->getConfig()->getProfiles(),
            );

            if (!$this->getConfig()->skipGenerateSystemTables()) {
                $outTableManifestOptions = new ManifestOptions();
                $outTableManifestOptions
                    ->setDestination($this->getConfig()->getOutputBucket() . '.profiles')
                    ->setIncremental(true);

                foreach (['id', 'name', 'webPropertyId', 'webPropertyName', 'accountId', 'accountName'] as $column) {
                    $outTableManifestOptions->addSchema(new ManifestOptionsSchema(
                        $column,
                        ['base' => ['type' => 'STRING']],
                        true,
                        $column === 'id',
                    ));
                }

                $this->getManifestManager()->writeTableManifest(
                    'profiles.csv',
                    $outTableManifestOptions,
                    $this->getConfig()->getDataTypeSupport()->usingLegacyManifest(),
                );

                $output = new Output(
                    $this->getDataDir(),
                    $this->getConfig()->getOutputBucket(),
                    $this->getConfig()->getDataTypeSupport()->usingLegacyManifest(),
                );

                $output->writeProfiles(
                    $output->createCsvFile('profiles'),
                    $this->getConfig()->getProfiles(),
                );
            }
        }

        if ($this->getConfig()->processProperties($this->getConfigDefinitionClass())) {
            $validProperties = $validator->validateProperties($this->getConfig()->getProperties());

            $this->getExtractor()->runProperties(
                $query,
                iterator_to_array($validProperties),
            );

            if (!$this->getConfig()->skipGenerateSystemTables()) {
                $outTableManifestOptions = new ManifestOptions();
                $outTableManifestOptions
                    ->setDestination($this->getConfig()->getOutputBucket() . '.properties')
                    ->setIncremental(true);

                foreach (['propertyKey', 'propertyName', 'accountKey', 'accountName'] as $column) {
                    $outTableManifestOptions->addSchema(new ManifestOptionsSchema(
                        $column,
                        ['base' => ['type' => 'STRING']],
                        true,
                        $column === 'propertyKey',
                    ));
                }

                $this->getManifestManager()->writeTableManifest(
                    'properties.csv',
                    $outTableManifestOptions,
                    $this->getConfig()->getDataTypeSupport()->usingLegacyManifest(),
                );

                $output = new Output(
                    $this->getDataDir(),
                    $this->getConfig()->getOutputBucket(),
                    $this->getConfig()->getDataTypeSupport()->usingLegacyManifest(),
                );
                $output->writeProperties(
                    $output->createCsvFile('properties'),
                    $this->getConfig()->getProperties(),
                );
            }
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

    protected function getPropertiesMetadata(): array
    {
        $properties = $this->getConfig()->getProperties();
        $viewId = $this->getConfig()->getQuery()['viewId'] ?? null;

        $result = [];
        try {
            $result = $this->getExtractor()->getPropertiesMetadata($properties, $viewId);
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
            case self::ACTION_GET_PROPERTIES_METADATA:
                return ConfigGetPropertiesMetadataDefinition::class;
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
            self::ACTION_GET_PROPERTIES_METADATA => 'getPropertiesMetadata',
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
            new Output(
                $this->getDataDir(),
                $this->getConfig()->getOutputBucket(),
                $this->getConfig()->getDataTypeSupport()->usingLegacyManifest(),
            ),
            $this->getLogger(),
        );
    }

    private function getGoogleRestApi(): RestApi
    {
        $serviceAccount = $this->getConfig()->getServiceAccount();
        if ($serviceAccount) {
            $this->getLogger()->info(sprintf(
                'Login with service account: "%s"',
                $serviceAccount['client_email'],
            ));
            $client = RestApi::createWithServiceAccount(
                $serviceAccount,
                [
                    'https://www.googleapis.com/auth/analytics.readonly',
                ],
                $this->getLogger(),
            );
        } else {
            $this->getLogger()->info('Login with OAuth');
            /** @var array{access_token?: string, refresh_token?: string}|null $tokenData */
            $tokenData = json_decode($this->getConfig()->getOAuthApiData(), true);
            if (!isset($tokenData['access_token'], $tokenData['refresh_token'])) {
                throw new UserException('The token data are broken. Please try to reauthorize.');
            }

            $client = RestApi::createWithOAuth(
                $this->getConfig()->getOAuthApiAppKey(),
                $this->getConfig()->getOAuthApiAppSecret(),
                $tokenData['access_token'],
                $tokenData['refresh_token'],
                $this->getLogger(),
            );
        }

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

        $response = (string) $e->getResponse()->getBody();
        if ($e->getCode() === 400) {
            throw new UserException($response !== '' ? $response : $e->getMessage());
        }

        if ($e->getCode() === 401) {
            throw new UserException('Expired or wrong credentials, please reauthorize.', 401, $e);
        }

        if ($e->getCode() === 403) {
            if (strtolower($e->getResponse()->getReasonPhrase()) === 'forbidden') {
                $this->getLogger()->warning(
                    'You don\'t have access to Google Analytics resource. ' .
                    'Probably you don\'t have access to profile, or profile doesn\'t exists anymore.',
                );
                return;
            } else {
                throw new UserException('Reason: ' . $e->getResponse()->getReasonPhrase(), 403, $e);
            }
        }

        if ($e->getCode() === 502) {
            throw new UserException(
                'Google API is temporary unavailable. Please try again later.',
                502,
                $e,
            );
        }

        if ($e->getCode() === 503) {
            throw new UserException('Google API error: ' . $e->getMessage(), 503, $e);
        }

        if ($e->getCode() === 429) {
            throw new UserException($e->getMessage());
        }

        if ($e instanceof ServerException && str_contains('internal_failure', $response)) {
            throw new UserException('Google API error: internal failure', 500, $e);
        }

        throw new ApplicationException($response, 500, $e);
    }
}
