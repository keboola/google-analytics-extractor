<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use GuzzleHttp\Psr7\Response;
use Keboola\Csv\CsvFile;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\Config;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Finder\Finder;

class ExtractorTest extends TestCase
{
    /** @phpcs:disable */
    private const NEXT_PAGE_TOKEN_1 = 'AOlqmtWnOMPDKi1Ja0CjfkpNKBiXwJHiqUfdBX5nXJ3MFvUquyKw8E48FLB_puDk_PuLNkPpNCzHsanY-HGO4hF16VB6mkkaHjXzoksmu93LS2bS2RO3FyUBxqmW2WA=';
    private const NEXT_PAGE_TOKEN_2 = 'b8pE9HJNvEv44XYxwVnl5GPAcrvqq-HbcfVH3XiSNf11zLrHa87o112Ucouj1g0MV1xFgnZSoxLXkzCIHFSjbfRYDWifQQa6IVYuN9YJ5bHVrjePOg%XEFpGfj7fhEBF';
    /** @phpcs:enable */

    private Extractor $extractor;

    private array $config;

    private string $dataDir;

    private TestLogger $logger;

    public function setUp(): void
    {
        $this->dataDir = __DIR__ . '/../../../../tests/data';
        $this->config = $this->getConfig();
        $this->logger = new TestLogger();
        $client = new Client(
            new RestApi(
                (string) getenv('CLIENT_ID'),
                (string) getenv('CLIENT_SECRET'),
                (string) getenv('ACCESS_TOKEN'),
                (string) getenv('REFRESH_TOKEN'),
            ),
            $this->logger,
            [],
        );
        $output = new Output($this->dataDir, 'outputBucket');
        $this->extractor = new Extractor($client, $output, $this->logger);
    }

    private function getConfig(string $configPostfix = ''): array
    {
        $config = json_decode(
            (string) file_get_contents(
                sprintf(
                    '%s/config%s.json',
                    $this->dataDir,
                    $configPostfix,
                ),
            ),
            true,
        );
        return $config;
    }

    public function testRunProperties(): void
    {
        $this->config = $this->getConfig('_properties');
        $config = new Config($this->config, new ConfigDefinition());
        $parameters = $config->getParameters();
        $properties = $parameters['properties'];

        $this->extractor->runProperties($parameters, $properties);

        $manifestFiles = $this->getManifestFiles($parameters['outputTable']);
        Assert::assertNotEmpty($manifestFiles);

        $dateCol = null;
        /** @var \SplFileInfo $manifestFile */
        foreach ($manifestFiles as $manifestFile) {
            Assert::assertFileExists((string) $manifestFile->getRealPath());
            $manifestContent = (string) file_get_contents((string) $manifestFile->getRealPath());
            Assert::assertNotEmpty($manifestContent);
            $manifest = (array) json_decode($manifestContent, true, 512, JSON_THROW_ON_ERROR);

            $dimensions = $parameters['query']['dimensions'];
            $metrics = $parameters['query']['metrics'];

            foreach ($dimensions as $dimension) {
                Assert::assertContains(
                    $dimension['name'],
                    $manifest['columns'],
                );
            }
            foreach ($metrics as $metric) {
                Assert::assertContains(
                    $metric['name'],
                    $manifest['columns'],
                );
            }

            $dateCol = array_search('date', $manifest['columns']);
        }

        $outputFiles = $this->getOutputFiles($parameters['outputTable']);
        Assert::assertNotEmpty($outputFiles);

        /** @var \SplFileInfo $outputFile */
        foreach ($outputFiles as $outputFile) {
            Assert::assertFileExists((string) $outputFile->getRealPath());
            Assert::assertNotEmpty((string) file_get_contents((string) $outputFile->getRealPath()));

            $csv = new CsvFile((String) $outputFile->getRealPath());
            // check date format
            $csv->next();
            $row = $csv->current();
            Assert::assertStringContainsString('-', $row[$dateCol]);
        }
    }


    public function testRunPropertiesFilter(): void
    {
        $this->config = $this->getConfig('_properties');
        $config = new Config($this->config, new ConfigDefinition());
        $parameters = $config->getParameters();
        $properties = $parameters['properties'];
        $properties[] = [
            'accountKey' => 'accounts/123456789',
            'accountName' => 'Keboola fake name',
            'propertyKey' => 'properties/123456789',
            'propertyName' => 'Fake property',
        ];
        $parameters['query']['viewId'] = 'properties/255885884';

        $this->extractor->runProperties($parameters, $properties);

        $outputFiles = $this->getOutputFiles($parameters['outputTable']);
        Assert::assertNotEmpty($outputFiles);

        Assert::assertTrue($this->logger->hasInfo('Skipping property "Fake property".'));
    }

    public function testRunEmptyResult(): void
    {
        $config = new Config($this->config, new ConfigDefinition());
        $parameters = $config->getParameters();

        $profiles = $parameters['profiles'];
        unset($parameters['query']);

        $this->extractor->runProfiles($parameters, $profiles[0]);

        Assert::assertTrue(true);
    }

    public function _testGetProfilesProperties(): void
    {
        $restApi = $this->createMock(RestApi::class);
        $restApi
            ->method('request')
            ->with($this->logicalOr(
                sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, Client::PAGE_SIZE),
                Client::ACCOUNT_WEB_PROPERTIES_URL,
                sprintf('%s?max-results=%d', Client::ACCOUNT_PROFILES_URL, Client::PAGE_SIZE),
                Client::ACCOUNTS_URL,
            ))
            ->will($this->returnCallback([$this, 'returnMockServerRequest']))
        ;

        $logger = new NullLogger();
        $client = new Client(
            $restApi,
            $logger,
            [],
        );
        $output = new Output($this->dataDir, 'outputBucket');
        $extractor = new Extractor($client, $output, $logger);

        $this->assertEquals(
            [
                'profiles' => [
                    [
                        'id' => '88156763',
                        'accountId' => '52541130',
                        'webPropertyId' => 'UA-52541130-1',
                        'name' => 'All Web Site Data',
                        'eCommerceTracking' => false,
                        'webPropertyName' => 'status.keboola.com',
                        'accountName' => 'Keboola Status Blog',
                    ],
                    [
                        'id' => '184062725',
                        'accountId' => '128209249',
                        'webPropertyId' => 'UA-128209249-1',
                        'name' => 'All Web Site Data',
                        'eCommerceTracking' => true,
                        'webPropertyName' => 'Website',
                        'accountName' => 'Keboola Website',
                    ],
                ],
                'properties' => [
                    [
                        'accountKey' => 'accounts/185283969',
                        'accountName' => 'Ondřej Jodas',
                        'propertyKey' => 'properties/255885884',
                        'propertyName' => 'users',
                    ],
                ],
                'messages' => [],
            ],
            $extractor->getProfilesPropertiesAction(),
        );
    }

    public function testGetEmptyProfilesProperties(): void
    {
        $restApi = $this->createMock(RestApi::class);
        $restApi
            ->method('request')
            ->with($this->logicalOr(
                sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, Client::PAGE_SIZE),
                sprintf('%s?max-results=%d', Client::ACCOUNT_PROFILES_URL, Client::PAGE_SIZE),
                Client::ACCOUNT_WEB_PROPERTIES_URL,
                Client::ACCOUNTS_URL,
            ))
            ->will($this->returnCallback([$this, 'returnMockServerRequestEmptyResponse']))
        ;

        $logger = new NullLogger();
        $client = new Client(
            $restApi,
            $logger,
            [],
        );
        $output = new Output($this->dataDir, 'outputBucket');
        $extractor = new Extractor($client, $output, $logger);

        $this->assertEquals(
            [
                'profiles' => [],
                'properties' => [],
                'messages' => [],
            ],
            $extractor->getProfilesPropertiesAction(),
        );
    }

    public function testGetAccountProperties(): void
    {
        $restApi = $this->createMock(RestApi::class);

        $baseUrl = sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, 1);
        $firstPage = $baseUrl;
        $secondPage = sprintf('%s&pageToken=%s', $baseUrl, self::NEXT_PAGE_TOKEN_1);
        $thirdPage = sprintf('%s&pageToken=%s', $baseUrl, self::NEXT_PAGE_TOKEN_2);
        $restApi
            ->method('request')
            ->with($this->logicalOr($firstPage, $secondPage, $thirdPage))
            ->will($this->returnCallback([$this, 'returnMockServerAccountPropertiesResponse']))
        ;

        $logger = new NullLogger();
        $client = new Client(
            $restApi,
            $logger,
            [],
        );

        $this->assertEquals(
            [
                1 => [
                    'name' => 'accountSummaries/4475423',
                    'account' => 'accounts/4475423',
                    'displayName' => 'Keboola Component Portal',
                    'propertySummaries' => [
                        [
                            'property' => 'properties/331566540',
                            'displayName' => 'Adam Výborný',
                            'propertyType' => 'PROPERTY_TYPE_ORDINARY',
                            'parent' => 'accounts/4475423',
                        ],
                    ],
                ],
                0 => [
                    'name' => 'accountSummaries/4475422',
                    'account' => 'accounts/4475422',
                    'displayName' => 'Keboola Website',
                    'propertySummaries' => [
                        [
                            'property' => 'properties/331566539',
                            'displayName' => 'Ondřej Jodas',
                            'propertyType' => 'PROPERTY_TYPE_ORDINARY',
                            'parent' => 'accounts/4475422',
                        ],
                    ],
                ],
                2 => [
                    'name' => 'accountSummaries/4475424',
                    'account' => 'accounts/4475424',
                    'displayName' => 'Keboola Developer Documentation',
                    'propertySummaries' => [
                        [
                            'property' => 'properties/331566541',
                            'displayName' => 'Ondřej Jodas',
                            'propertyType' => 'PROPERTY_TYPE_ORDINARY',
                            'parent' => 'accounts/4475424',
                        ],
                    ],
                ],
            ],
            $client->getAccountProperties(1),
        );
    }

    public function returnMockServerRequest(string $url): Response
    {
        /** @phpcs:disable */
        switch ($url) {
            case Client::ACCOUNT_WEB_PROPERTIES_URL:
                return new Response(
                    200,
                    [],
                    '{"kind":"analytics#webproperties","username":"ondrej.jodas@keboola.com","totalResults":2,"startIndex":1,"itemsPerPage":1000,"items":[{"id":"UA-52541130-1","accountId":"52541130","name":"status.keboola.com"},{"id":"UA-128209249-1","accountId":"128209249","name":"Website"}]}'
                );
            case sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, Client::PAGE_SIZE):
                return new Response(
                    200,
                    [],
                    '{"accountSummaries":[{"name":"accountSummaries/128209249","account":"accounts/128209249","displayName":"Keboola Website"},{"name":"accountSummaries/185283969","account":"accounts/185283969","displayName":"Ondřej Jodas","propertySummaries":[{"property":"properties/255885884","displayName":"users"}]},{"name":"accountSummaries/52541130","account":"accounts/52541130","displayName":"Keboola Status Blog"}]}'
                );
            case sprintf('%s?max-results=%d', Client::ACCOUNT_PROFILES_URL, Client::PAGE_SIZE):
                return new Response(
                    200,
                    [],
                    '{"kind":"analytics#profiles","username":"ondrej.jodas@keboola.com","totalResults":2,"startIndex":1,"itemsPerPage":1000,"items":[{"id":"88156763","accountId":"52541130","webPropertyId":"UA-52541130-1","name":"All Web Site Data","eCommerceTracking":false},{"id":"184062725","accountId":"128209249","webPropertyId":"UA-128209249-1","name":"All Web Site Data","eCommerceTracking":true}]}'
                );
            case Client::ACCOUNTS_URL:
                return new Response(
                    200,
                    [],
                    '{"kind":"analytics#accounts","username":"ondrej.jodas@keboola.com","totalResults":3,"startIndex":1,"itemsPerPage":1000,"items":[{"id":"52541130","name":"Keboola Status Blog"},{"id":"128209249","name":"Keboola Website"},{"id":"185283969","name":"Ondřej Jodas"}]}'
                );
        }
        /** @phpcs:enable */
        return new Response(200, [], '');
    }

    public function returnMockServerRequestEmptyResponse(string $url): Response
    {
        return new Response(200, [], '');
    }

    public function returnMockServerAccountPropertiesResponse(string $url): Response
    {
        /** @phpcs:disable */
        switch ($url) {
            case sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, 1):
                return new Response(
                    200,
                    [],
                    '{"accountSummaries":[{"name":"accountSummaries/4475422","account":"accounts/4475422","displayName":"Keboola Website","propertySummaries":[{"property":"properties/331566539","displayName":"Ondřej Jodas","propertyType":"PROPERTY_TYPE_ORDINARY","parent":"accounts/4475422"}]}],"nextPageToken":"AOlqmtWnOMPDKi1Ja0CjfkpNKBiXwJHiqUfdBX5nXJ3MFvUquyKw8E48FLB_puDk_PuLNkPpNCzHsanY-HGO4hF16VB6mkkaHjXzoksmu93LS2bS2RO3FyUBxqmW2WA="}'
                );
            case sprintf('%s?pageSize=%d&pageToken=%s', Client::ACCOUNT_PROPERTIES_URL, 1, self::NEXT_PAGE_TOKEN_1):
                return new Response(
                    200,
                    [],
                    '{"accountSummaries":[{"name":"accountSummaries/4475423","account":"accounts/4475423","displayName":"Keboola Component Portal","propertySummaries":[{"property":"properties/331566540","displayName":"Adam Výborný","propertyType":"PROPERTY_TYPE_ORDINARY","parent":"accounts/4475423"}]}],"nextPageToken":"b8pE9HJNvEv44XYxwVnl5GPAcrvqq-HbcfVH3XiSNf11zLrHa87o112Ucouj1g0MV1xFgnZSoxLXkzCIHFSjbfRYDWifQQa6IVYuN9YJ5bHVrjePOg%XEFpGfj7fhEBF"}'
                );
            case sprintf('%s?pageSize=%s&pageToken=%s', Client::ACCOUNT_PROPERTIES_URL, 1, self::NEXT_PAGE_TOKEN_2):
                return new Response(
                    200,
                    [],
                    '{"accountSummaries":[{"name":"accountSummaries/4475424","account":"accounts/4475424","displayName":"Keboola Developer Documentation","propertySummaries":[{"property":"properties/331566541","displayName":"Ondřej Jodas","propertyType":"PROPERTY_TYPE_ORDINARY","parent":"accounts/4475424"}]}]}'
                );
        }
        /** @phpcs:enable */
        return new Response(200, [], '');
    }
    private function getOutputFiles(string $queryName): Finder
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
            ->name('/^' . $queryName . '.*\.csv$/i');
    }

    private function getManifestFiles(string $queryName): Finder
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
            ->name('/^' . $queryName . '.*\.csv.manifest$/i')
            ;
    }
}
