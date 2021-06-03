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
            $this->logger
        );
        $output = new Output($this->dataDir);
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
                Client::ACCOUNT_PROPERTIES_URL,
                Client::ACCOUNT_WEB_PROPERTIES_URL,
                Client::ACCOUNT_PROFILES_URL,
                Client::ACCOUNTS_URL
            ))
            ->will($this->returnCallback(array($this, 'returnMockServerRequest')))
        ;

        $logger = new NullLogger();
        $client = new Client(
            $restApi,
            $logger
        );
        $output = new Output($this->dataDir);
        $extractor = new Extractor($client, $output, $logger);

        $this->assertEquals(
            [
                'profiles' => [
                    [
                        'id' => '88156763',
                        'accountId' => '52541130',
                        'webPropertyId' => 'UA-52541130-1',
                        'name' => 'All Web Site Data',
                        'webPropertyName' => 'status.keboola.com',
                        'accountName' => 'Keboola Status Blog',
                    ],
                    [
                        'id' => '184062725',
                        'accountId' => '128209249',
                        'webPropertyId' => 'UA-128209249-1',
                        'name' => 'All Web Site Data',
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
            ],
            $extractor->getProfilesPropertiesAction()
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
            case Client::ACCOUNT_PROPERTIES_URL:
                return new Response(
                    200,
                    [],
                    '{"accountSummaries":[{"name":"accountSummaries/128209249","account":"accounts/128209249","displayName":"Keboola Website"},{"name":"accountSummaries/185283969","account":"accounts/185283969","displayName":"Ondřej Jodas","propertySummaries":[{"property":"properties/255885884","displayName":"users"}]},{"name":"accountSummaries/52541130","account":"accounts/52541130","displayName":"Keboola Status Blog"}]}'
                );
            case Client::ACCOUNT_PROFILES_URL:
                return new Response(
                    200,
                    [],
                    '{"kind":"analytics#profiles","username":"ondrej.jodas@keboola.com","totalResults":2,"startIndex":1,"itemsPerPage":1000,"items":[{"id":"88156763","accountId":"52541130","webPropertyId":"UA-52541130-1","name":"All Web Site Data"},{"id":"184062725","accountId":"128209249","webPropertyId":"UA-128209249-1","name":"All Web Site Data"}]}'
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

    private function getOutputFiles(string $queryName): Finder
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
            ->name('/^' . $queryName . '.*\.csv$/i');
    }
}
