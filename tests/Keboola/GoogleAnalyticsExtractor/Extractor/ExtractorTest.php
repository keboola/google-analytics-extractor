<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 18/04/16
 * Time: 18:38
 */
namespace Keboola\GoogleAnalyticsExtractor\Test;

use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Monolog\Logger;
use Symfony\Component\Yaml\Yaml;

class ExtractorTest extends \PHPUnit_Framework_TestCase
{
    /** @var Extractor */
    private $extractor;

    private $config;

    public function setUp()
    {
        $this->config = $this->getConfig();
        $client = new Client(new RestApi(
            getenv('CLIENT_ID'),
            getenv('CLIENT_SECRET'),
            getenv('ACCESS_TOKEN'),
            getenv('REFRESH_TOKEN')
        ));
        $output = new Output(ROOT_PATH . '/tests/data', $this->config['parameters']['outputBucket']);
        $logger = new Logger(APP_NAME);
        $this->extractor = new Extractor($client, $output, $logger);
    }

    private function getConfig()
    {
        return Yaml::parse(file_get_contents(ROOT_PATH . '/tests/data/config.yml'));
    }

    public function testRun()
    {
        $queries = $this->config['parameters']['queries'];
        $profiles = $this->config['parameters']['profiles'];

        $this->extractor->run($queries, $profiles[0]);

        $outputFile = ROOT_PATH . '/tests/data/out/tables/' . $queries[0]['outputTable'] . '.csv';
        $this->assertFileExists($outputFile);
        $this->assertNotEmpty(file_get_contents($outputFile));
    }
}
