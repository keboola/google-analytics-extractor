<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 19/04/16
 * Time: 10:59
 */
namespace Keboola\GoogleAnalyticsExtractor\Test;

use Composer\Package\RootAliasPackage;
use Keboola\GoogleAnalyticsExtractor\Application;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
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

    public function testAppRun()
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

        $this->assertEquals($this->config['parameters']['outputBucket'] . '.users.csv', $usersManifest['destination']);
        $this->assertEquals($this->config['parameters']['outputBucket'] . '.profiles.csv', $profilesManifest['destination']);
    }

    public function testAppSample()
    {
        $this->config['action'] = 'sample';
        $this->application = new Application($this->config);

        $result = $this->application->run();

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('viewId', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rowCount', $result);
        $this->assertEquals('success', $result['status']);
    }

    public function testAppSegments()
    {
        $this->config['action'] = 'segments';
        $this->application = new Application($this->config);

        $result = $this->application->run();

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('success', $result['status']);
    }

    public function testAppUserException()
    {
        $this->expectException('Keboola\GoogleAnalyticsExtractor\Exception\UserException');

        $this->config = $this->getConfig();
        // unset segment dimension to trigger API error
        unset($this->config['parameters']['queries'][1]['query']['dimensions'][1]);
        $this->application = new Application($this->config);
        $this->application->run();
    }

    public function testRun()
    {
        $process = $this->runProcess();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testRunSampleAction()
    {
        $this->config['action'] = 'sample';
        $process = $this->runProcess();
        $this->assertEquals(0, $process->getExitCode());

        $output = json_decode($process->getOutput(), true);
        $this->assertArrayHasKey('status', $output);
        $this->assertArrayHasKey('viewId', $output);
        $this->assertArrayHasKey('data', $output);
        $this->assertArrayHasKey('rowCount', $output);
        $this->assertEquals('success', $output['status']);
        $this->assertNotEmpty($output['viewId']);
        $this->assertNotEmpty($output['data']);
        $this->assertNotEmpty($output['rowCount']);
    }

    public function testRunSegmentsAction()
    {
        $this->config['action'] = 'segments';
        $process = $this->runProcess();
        $this->assertEquals(0, $process->getExitCode());

        $output = json_decode($process->getOutput(), true);
        $this->assertArrayHasKey('status', $output);
        $this->assertArrayHasKey('data', $output);
        $this->assertEquals('success', $output['status']);
        $this->assertNotEmpty($output['data']);
        $segment = $output['data'][0];
        $this->assertArrayHasKey('id', $segment);
        $this->assertArrayHasKey('kind', $segment);
        $this->assertArrayHasKey('segmentId', $segment);
        $this->assertArrayHasKey('name', $segment);
    }

    public function testActionUserException()
    {
        $this->config['action'] = 'sample';
        $this->config['parameters']['queries'][0]['query']['metrics'] = [
            ['expression' => 'ga:nonexistingmetric']
        ];

        $process = $this->runProcess();

        $this->assertEquals(1, $process->getExitCode());
        $output = json_decode($process->getOutput(), true);
        $this->assertEquals('error', $output['status']);
        $this->assertEquals('User Error', $output['error']);
        $this->assertNotEmpty($output['message']);
    }

    public function testActionAuthUserException()
    {
        $this->config['action'] = 'sample';
        unset($this->config['authorization']);

        $process = $this->runProcess();

        $this->assertEquals(1, $process->getExitCode());
        $output = json_decode($process->getOutput(), true);
        $this->assertEquals('error', $output['status']);
        $this->assertEquals('User Error', $output['error']);
        $this->assertNotEmpty($output['message']);
    }

    public function testRunEmptyResult()
    {
        // set metric that will return no data
        unset($this->config['parameters']['queries'][1]);
        $this->config['parameters']['queries'][0]['query']['metrics'] = [
            ['expression' => 'ga:adxRevenue']
        ];
        $process = $this->runProcess();
        $this->assertEquals(0, $process->getExitCode());

        $usersOutputPath = ROOT_PATH . '/tests/data/out/tables/users.csv';
        $usersManifestPath = $usersOutputPath . '.manifest';

        $this->assertFileNotExists($usersOutputPath);
        $this->assertFileNotExists($usersManifestPath);
    }

    public function testSampleActionEmptyResult()
    {
        $this->config['action'] = 'sample';
        // set metric that will return no data
        unset($this->config['parameters']['queries'][1]);
        $this->config['parameters']['queries'][0]['query']['metrics'] = [
            ['expression' => 'ga:adxRevenue']
        ];
        $usersOutputPath = ROOT_PATH . '/tests/data/out/tables/users.csv';
        $usersManifestPath = $usersOutputPath . '.manifest';

        $process = $this->runProcess();
        $output = json_decode($process->getOutput(), true);

        $this->assertEquals(0, $process->getExitCode());
        $this->assertEmpty($output['data']);
        $this->assertEquals('success', $output['status']);
        $this->assertEquals(0, $output['rowCount']);
        $this->assertFileNotExists($usersOutputPath);
        $this->assertFileNotExists($usersManifestPath);
    }

    private function runProcess()
    {
        $dataPath = '/tmp/data-test';
        $fs = new Filesystem();
        $fs->remove($dataPath);
        $fs->mkdir($dataPath);
        $fs->mkdir($dataPath . '/out/tables');

        $yaml = new Yaml();
        file_put_contents($dataPath . '/config.yml', $yaml->dump($this->config));

        $process = new Process(sprintf('php run.php --data=%s', $dataPath));
        $process->run();

        return $process;
    }
}
