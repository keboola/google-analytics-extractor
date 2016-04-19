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

    public function setUp()
    {
        $client = new Client(new RestApi(
            getenv('CLIENT_ID'),
            getenv('CLIENT_SECRET'),
            getenv('ACCESS_TOKEN'),
            getenv('REFRESH_TOKEN')
        ));
        $output = new Output(ROOT_PATH . '/tests/data');
        $logger = new Logger(APP_NAME);
        $this->extractor = new Extractor($client, $output, $logger);
    }

    private function getConfig()
    {
        return Yaml::parse(file_get_contents(ROOT_PATH . '/tests/data/config.yml'));
    }

    public function testRun()
    {
        $config = $this->getConfig();
        $queries = $config['parameters']['queries'];
        $profiles = $config['parameters']['profiles'];

        $this->extractor->run($queries, $profiles[0]);
    }
}
