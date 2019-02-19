<?php

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Keboola\Csv\CsvFile;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigDefinition;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Keboola\GoogleAnalyticsExtractor\Logger\Logger;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Finder\Finder;

class ExtractorTest extends \PHPUnit_Framework_TestCase
{
    /** @var Extractor */
    private $extractor;

    private $config;

    private $dataDir;

    public function setUp()
    {
        $this->dataDir = __DIR__ . '/../../../../tests/data';
        $this->config = $this->getConfig();
        $logger = new Logger('ex-google-analytics');
        $client = new Client(
            new RestApi(
                getenv('CLIENT_ID'),
                getenv('CLIENT_SECRET'),
                getenv('ACCESS_TOKEN'),
                getenv('REFRESH_TOKEN')
            ),
            $logger
        );
        $output = new Output($this->dataDir, $this->config['parameters']['outputBucket']);
        $this->extractor = new Extractor($client, $output, $logger);
    }

    private function getConfig()
    {
        $config = json_decode(file_get_contents($this->dataDir . '/config.json'), true);
        $config['parameters']['data_dir'] = $this->dataDir;
        return $config;
    }

    public function testRun()
    {
        $parameters = $this->validateParameters($this->config['parameters']);
        $queries = $parameters['queries'];
        $profiles = $parameters['profiles'];

        $this->extractor->run($queries, [$profiles[0]]);

        $outputFiles = $this->getOutputFiles($queries[0]['outputTable']);
        $this->assertNotEmpty($outputFiles);

        /** @var \SplFileInfo $outputFile */
        foreach ($outputFiles as $outputFile) {
            $this->assertFileExists($outputFile->getRealPath());
            $this->assertNotEmpty(file_get_contents($outputFile->getRealPath()));

            $csv = new CsvFile($outputFile->getRealPath());
            $csv->next();
            $header = $csv->current();

            // check CSV header
            $dimensions = $queries[0]['query']['dimensions'];
            $metrics = $queries[0]['query']['metrics'];
            foreach ($dimensions as $dimension) {
                $this->assertContains(str_replace('ga:', '', $dimension['name']), $header);
            }
            foreach ($metrics as $metric) {
                $this->assertContains(str_replace('ga:', '', $metric['expression']), $header);
            }

            // check date format
            $csv->next();
            $row = $csv->current();
            $dateCol = array_search('date', $header);
            $this->assertContains('-', $row[$dateCol]);
        }
    }

    public function testRunEmptyResult()
    {
        $parameters = $this->validateParameters($this->config['parameters']);
        $profiles = $parameters['profiles'];

        $this->extractor->run([], $profiles[0]);
    }

    private function getOutputFiles($queryName)
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
            ->name('/^' . $queryName . '.*\.csv$/i');
    }

    private function getManifestFiles($queryName)
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
            ->name('/^' . $queryName . '.*\.manifest$/i');
    }

    private function validateParameters($parameters)
    {
        return (new Processor())->processConfiguration(new ConfigDefinition(), [$parameters]);
    }
}
