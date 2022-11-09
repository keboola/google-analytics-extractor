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
                (string) getenv('REFRESH_TOKEN')
            ),
            $this->logger,
            []
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
                    $configPostfix
                )
            ),
            true
        );
        return $config;
    }

    public function testRunProfiles(): void
    {
        $config = new Config($this->config, new ConfigDefinition());
        $parameters = $config->getParameters();
        $profiles = $parameters['profiles'];

        $this->extractor->runProfiles($parameters, [$profiles[0]]);

        $outputFiles = $this->getOutputFiles($parameters['outputTable']);
        Assert::assertNotEmpty($outputFiles);

        /** @var \SplFileInfo $outputFile */
        foreach ($outputFiles as $outputFile) {
            Assert::assertFileExists((string) $outputFile->getRealPath());
            Assert::assertNotEmpty((string) file_get_contents((string) $outputFile->getRealPath()));

            $csv = new CsvFile((String) $outputFile->getRealPath());
            $csv->next();
            $header = $csv->current();

            // check CSV header
            $dimensions = $parameters['query']['dimensions'];
            $metrics = $parameters['query']['metrics'];
            foreach ($dimensions as $dimension) {
                Assert::assertContains(
                    str_replace('ga:', '', $dimension['name']),
                    $header
                );
            }
            foreach ($metrics as $metric) {
                Assert::assertContains(
                    str_replace('ga:', '', $metric['expression']),
                    $header
                );
            }

            // check date format
            $csv->next();
            $row = $csv->current();
            $dateCol = array_search('date', $header);
            Assert::assertStringContainsString('-', $row[$dateCol]);
        }
    }

    public function testRunProperties(): void
    {
        $this->config = $this->getConfig('_properties');
        $config = new Config($this->config, new ConfigDefinition());
        $parameters = $config->getParameters();
        $properties = $parameters['properties'];

        $this->extractor->runProperties($parameters, $properties);

        $outputFiles = $this->getOutputFiles($parameters['outputTable']);
        Assert::assertNotEmpty($outputFiles);

        /** @var \SplFileInfo $outputFile */
        foreach ($outputFiles as $outputFile) {
            Assert::assertFileExists((string) $outputFile->getRealPath());
            Assert::assertNotEmpty((string) file_get_contents((string) $outputFile->getRealPath()));

            $csv = new CsvFile((String) $outputFile->getRealPath());
            $csv->next();
            $header = $csv->current();

            // check CSV header
            $dimensions = $parameters['query']['dimensions'];
            $metrics = $parameters['query']['metrics'];

            foreach ($dimensions as $dimension) {
                Assert::assertContains(
                    $dimension['name'],
                    $header
                );
            }
            foreach ($metrics as $metric) {
                Assert::assertContains(
                    $metric['name'],
                    $header
                );
            }

            // check date format
            $csv->next();
            $row = $csv->current();
            $dateCol = array_search('date', $header);
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

    public function testGetProfilesProperties(): void
    {
        $restApi = $this->createMock(RestApi::class);
        $restApi
            ->method('request')
            ->with($this->logicalOr(
                sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, Client::PAGE_SIZE),
                Client::ACCOUNT_WEB_PROPERTIES_URL,
                Client::ACCOUNT_PROFILES_URL,
                Client::ACCOUNTS_URL
            ))
            ->will($this->returnCallback(array($this, 'returnMockServerRequest')))
        ;

        $logger = new NullLogger();
        $client = new Client(
            $restApi,
            $logger,
            []
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
            $extractor->getProfilesPropertiesAction()
        );
    }

    public function testGetEmptyProfilesProperties(): void
    {
        $restApi = $this->createMock(RestApi::class);
        $restApi
            ->method('request')
            ->with($this->logicalOr(
                sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, Client::PAGE_SIZE),
                Client::ACCOUNT_WEB_PROPERTIES_URL,
                Client::ACCOUNT_PROFILES_URL,
                Client::ACCOUNTS_URL
            ))
            ->will($this->returnCallback(array($this, 'returnMockServerRequestEmptyResponse')))
        ;

        $logger = new NullLogger();
        $client = new Client(
            $restApi,
            $logger,
            []
        );
        $output = new Output($this->dataDir, 'outputBucket');
        $extractor = new Extractor($client, $output, $logger);

        $this->assertEquals(
            [
                'profiles' => [],
                'properties' => [],
                'messages' => [],
            ],
            $extractor->getProfilesPropertiesAction()
        );
    }

    public function testGetAccountProperties(): void
    {
        $restApi = $this->createMock(RestApi::class);
        $firstPage = sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, 1);
        $secondPage = sprintf('%s?pageSize=%d&pageToken=%s', Client::ACCOUNT_PROPERTIES_URL, 1, 'kfmsek234fmkwe');
        $thirdPage = sprintf('%s?pageSize=%d&pageToken=%s', Client::ACCOUNT_PROPERTIES_URL, 1, 'm8jrewkf73fmlo');
        $restApi
            ->method('request')
            ->with($this->logicalOr($firstPage, $secondPage, $thirdPage))
            ->will($this->returnCallback([$this, 'returnMockServerAccountPropertiesResponse']))
        ;

        $logger = new NullLogger();
        $client = new Client(
            $restApi,
            $logger,
            []
        );

        "84": {
        "name": "accountSummaries\/90391784",
    "account": "accounts\/90391784",
    "displayName": "IV Health",
    "propertySummaries": [
      {
          "property": "properties\/332627208",
        "displayName": "IV Health GA4",
        "propertyType": "PROPERTY_TYPE_ORDINARY",
        "parent": "accounts\/90391784"
      }
    ]
  },
  "85": {
        "name": "accountSummaries\/9324019",
    "account": "accounts\/9324019",
    "displayName": "coffeeschool.com.au",
    "propertySummaries": [
      {
          "property": "properties\/319882480",
        "displayName": "https:\/\/coffeeschool.com.au - GA4",
        "propertyType": "PROPERTY_TYPE_ORDINARY",
        "parent": "accounts\/9324019"
      }
    ]
  },
  "86": {
        "name": "accountSummaries\/98335895",
    "account": "accounts\/98335895",
    "displayName": "Nash Advisory",
    "propertySummaries": [
      {
          "property": "properties\/331586015",
        "displayName": "nashadvisory.com.au - GA4",
        "propertyType": "PROPERTY_TYPE_ORDINARY",
        "parent": "accounts\/98335895"
      }
    ]
  }

        $this->assertEquals(
            [
                1 => [
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
                4 => [
                    [
                        'accountKey' => 'accounts/185283969',
                        'accountName' => 'Ondřej Jodas',
                        'propertyKey' => 'properties/255885884',
                        'propertyName' => 'users',
                    ],
                ],
                7 => [],
            ],
            $client->getAccountProperties(1)
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
            case Client::ACCOUNT_PROFILES_URL:
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
                    '{"nextPageToken": "kfmsek234fmkwe", "accountSummaries":[{"name":"accountSummaries/128209249","account":"accounts/128209249","displayName":"Keboola Website"},{"name":"accountSummaries/185283969","account":"accounts/185283969","displayName":"Ondřej Jodas","propertySummaries":[{"property":"properties/255885884","displayName":"users"}]},{"name":"accountSummaries/52541130","account":"accounts/52541130","displayName":"Keboola Status Blog"}]}'
                );
            case sprintf('%s?pageSize=%d&pageToken=%s', Client::ACCOUNT_PROPERTIES_URL, 1, 'kfmsek234fmkwe'):
                return new Response(
                    200,
                    [],
                    '{"nextPageToken": "m8jrewkf73fmlo", "accountSummaries":[{"name":"accountSummaries/128209249","account":"accounts/128209249","displayName":"Keboola Website 2"},{"name":"accountSummaries/185283969","account":"accounts/185283969","displayName":"Ondřej Jodas 2","propertySummaries":[{"property":"properties/255885884","displayName":"users"}]},{"name":"accountSummaries/52541130","account":"accounts/52541130","displayName":"Keboola Status Blog"}]}'
                );
            case sprintf('%s?pageSize=%s&pageToken=%s', Client::ACCOUNT_PROPERTIES_URL, 1, 'm8jrewkf73fmlo'):
                return new Response(
                    200,
                    [],
                    '{"accountSummaries":[{"name":"accountSummaries/128209249","account":"accounts/128209249","displayName":"Keboola Website 3"},{"name":"accountSummaries/185283969","account":"accounts/185283969","displayName":"Ondřej Jodas 3","propertySummaries":[{"property":"properties/255885884","displayName":"users"}]},{"name":"accountSummaries/52541130","account":"accounts/52541130","displayName":"Keboola Status Blog"}]}'
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
}
