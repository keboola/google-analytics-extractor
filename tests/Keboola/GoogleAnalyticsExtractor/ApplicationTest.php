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

    public function setUp()
    {
        $this->application = new Application($this->getConfig());
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

        $this->assertFileExists($profilesOutputPath);
        $this->assertFileExists($profilesManifestPath);
        $this->assertFileExists($usersOutputPath);
        $this->assertFileExists($usersManifestPath);

        $profilesManifest = Yaml::parse(file_get_contents($profilesManifestPath));
        $usersManifest = Yaml::parse(file_get_contents($usersManifestPath));

        foreach ([$usersManifest, $profilesManifest] as $manifest) {
            $this->assertArrayHasKey('incremental', $manifest);
            $this->assertTrue($manifest['incremental']);
            $this->assertArrayHasKey('primary_key', $manifest);
            $this->assertEquals('id', $manifest['primary_key'][0]);
        }
    }
}
