<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Keboola\Csv\CsvFile;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\Config;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Finder\Finder;

class ExtractorTest extends TestCase
{
    private Extractor $extractor;

    private array $config;

    private string $dataDir;

    public function setUp(): void
    {
        $this->dataDir = __DIR__ . '/../../../../tests/data';
        $this->config = $this->getConfig();
        $logger = new NullLogger();
        $client = new Client(
            new RestApi(
                (string) getenv('CLIENT_ID'),
                (string) getenv('CLIENT_SECRET'),
                (string) getenv('ACCESS_TOKEN'),
                (string) getenv('REFRESH_TOKEN')
            ),
            $logger
        );
        $output = new Output($this->dataDir);
        $this->extractor = new Extractor($client, $output, $logger);
    }

    private function getConfig(): array
    {
        $config = json_decode((string) file_get_contents($this->dataDir . '/config.json'), true);
        return $config;
    }

    public function testRun(): void
    {
        $config = new Config($this->config, new ConfigDefinition());
        $parameters = $config->getParameters();
        $profiles = $parameters['profiles'];

        $this->extractor->run($parameters, [$profiles[0]]);

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

    public function testRunEmptyResult(): void
    {
        $config = new Config($this->config, new ConfigDefinition());
        $parameters = $config->getParameters();

        $profiles = $parameters['profiles'];
        unset($parameters['query']);

        $this->extractor->run($parameters, $profiles[0]);

        Assert::assertTrue(true);
    }

    private function getOutputFiles(string $queryName): Finder
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
            ->name('/^' . $queryName . '.*\.csv$/i');
    }
}
