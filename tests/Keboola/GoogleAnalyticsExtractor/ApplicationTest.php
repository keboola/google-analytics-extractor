<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 19/04/16
 * Time: 10:59
 */
namespace Keboola\GoogleAnalyticsExtractor\Test;

use Keboola\GoogleAnalyticsExtractor\Application;
use Symfony\Component\Yaml\Yaml;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    /** @var Application */
    private $application;

    private $config;

    public function setUp()
    {
        $this->config = $this->getConfig();
        $this->application = new Application($this->config);
    }

    private function getConfig()
    {
        $config = Yaml::parse(file_get_contents(ROOT_PATH . '/tests/data/config.yml'));
        $config['parameters']['data_dir'] = ROOT_PATH . '/tests/data/';
        $config['authorization']['oauth_api']['credentials'] = [
            'appKey' => getenv('CLIENT_ID'),
            '#appSecret' => getenv('CLIENT_SECRET'),
            '#data' => json_encode([
                'access_token' => getenv('ACCESS_TOKEN'),
                'refresh_token' => getenv('REFRESH_TOKEN')
            ])
        ];

        return $config;
    }

    public function testRun()
    {
        $this->application->run();

        $profilesOutputPath = ROOT_PATH . '/tests/data/out/tables/profiles.csv';
        $profilesManifestPath = $profilesOutputPath . '.manifest';
        $usersOutputPath = ROOT_PATH . '/tests/data/out/tables/users.csv';
        $usersManifestPath = $usersOutputPath . '.manifest';
        $organicOutputPath = ROOT_PATH . '/tests/data/out/tables/organicTraffic.csv';
        $organicManifestPath = $organicOutputPath . '.manifest';

        $this->assertFileExists($profilesOutputPath);
        $this->assertFileExists($profilesManifestPath);
        $this->assertFileExists($usersOutputPath);
        $this->assertFileExists($usersManifestPath);
        $this->assertFileExists($organicOutputPath);
        $this->assertFileExists($organicManifestPath);

        $profilesManifest = Yaml::parse(file_get_contents($profilesManifestPath));
        $usersManifest = Yaml::parse(file_get_contents($usersManifestPath));
        $organicManifest = Yaml::parse(file_get_contents($organicManifestPath));

        foreach ([$usersManifest, $profilesManifest, $organicManifest] as $manifest) {
            $this->assertArrayHasKey('destination', $manifest);
            $this->assertArrayHasKey('incremental', $manifest);
            $this->assertTrue($manifest['incremental']);
            $this->assertArrayHasKey('primary_key', $manifest);
            $this->assertEquals('id', $manifest['primary_key'][0]);
        }

        $this->assertEquals($this->config['parameters']['outputBucket'] . '.users.csv' , $usersManifest['destination']);
        $this->assertEquals($this->config['parameters']['outputBucket'] . '.profiles.csv' , $profilesManifest['destination']);
    }

    public function testSample()
    {
        $this->config['action'] = 'sample';
        $this->application = new Application($this->config);

        $result = $this->application->run();

        $this->assertArrayHasKey('viewId', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rowCount', $result);
    }
}