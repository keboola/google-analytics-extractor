<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 19/04/16
 * Time: 10:59
 */
namespace Keboola\GoogleAnalyticsExtractor\Test;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\Application;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
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

        $filesystem = new Filesystem();
        $filesystem->remove(ROOT_PATH . '/tests/data/out/tables');
        $filesystem->mkdir(ROOT_PATH . '/tests/data/out/tables');
    }

    private function getConfig($suffix = '')
    {
        $config = Yaml::parse(file_get_contents(ROOT_PATH . '/tests/data/config' . $suffix . '.yml'));
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

        $profiles = $this->getOutputFiles('profiles');
        $profilesManifests = $this->getManifestFiles('profiles');

        $users = $this->getOutputFiles('users');
        $usersManifests = $this->getManifestFiles('users');

        $organic = $this->getOutputFiles('organic');
        $organicManifests = $this->getManifestFiles('organic');

        $totals = $this->getOutputFiles('totals');
        $totalsManifest = $this->getManifestFiles('totals');

        $manifests = $this->getManifestFiles('');

        $this->assertEquals(1, count($profiles));
        $this->assertEquals(1, count($profilesManifests));

        $this->assertEquals(1, count($users));
        $this->assertEquals(1, count($usersManifests));

        $this->assertEquals(1, count($organic));
        $this->assertEquals(1, count($organicManifests));

        $this->assertEquals(1, count($totals));
        $this->assertEquals(1, count($totalsManifest));

        foreach ($profilesManifests as $profilesManifestFile) {
            $profilesManifest = Yaml::parse(file_get_contents($profilesManifestFile));
            $this->assertEquals($this->config['parameters']['outputBucket'] . '.profiles', $profilesManifest['destination']);
        }

        foreach ($usersManifests as $usersManifestFile) {
            $usersManifest = Yaml::parse(file_get_contents($usersManifestFile));
            $this->assertEquals($this->config['parameters']['outputBucket'] . '.users', $usersManifest['destination']);
        }

        foreach ($organicManifests as $organicManifestFile) {
            $organicManifest = Yaml::parse(file_get_contents($organicManifestFile));
            $this->assertEquals($this->config['parameters']['outputBucket'] . '.organicTraffic', $organicManifest['destination']);
        }

        foreach ($manifests as $manifestFile) {
            $manifest = Yaml::parse(file_get_contents($manifestFile));
            $this->assertArrayHasKey('destination', $manifest);
            $this->assertArrayHasKey('incremental', $manifest);
            $this->assertTrue($manifest['incremental']);
            $this->assertArrayHasKey('primary_key', $manifest);
            $this->assertEquals('id', $manifest['primary_key'][0]);
        }

        $totalsDataArr = [];
        foreach ($totals as $file) {
            $totalsData = new CsvFile($file->getPathname());
            $totalsData->next();

            while ($totalsData->current()) {
                $totalsDataArr[] = $totalsData->current();
                $totalsData->next();
            }
        }

        $this->assertCount(3, $totalsDataArr);
        $profileId1 = $this->config['parameters']['profiles'][0]['id'];
        $profileId2 = $this->config['parameters']['profiles'][1]['id'];
        $this->assertEquals($profileId1, $totalsDataArr[1][1]);
        $this->assertEquals($profileId2, $totalsDataArr[2][1]);
        $this->assertEquals('id', $totalsDataArr[0][0]);
        $this->assertEquals('idProfile', $totalsDataArr[0][1]);
        $this->assertEquals('year', $totalsDataArr[0][2]);
        $this->assertEquals('users', $totalsDataArr[0][3]);
        $this->assertEquals('sessions', $totalsDataArr[0][4]);
        $this->assertEquals('pageviews', $totalsDataArr[0][5]);
        $this->assertEquals('bounces', $totalsDataArr[0][6]);
    }

    public function testAppRunAntisampling()
    {
        $this->config = $this->getConfig('_antisampling');
        $this->application = new Application($this->config);
        $this->application->run();

        $dailyWalk = $this->getOutputFiles('dailyWalk');
        $this->assertEquals(1, count($dailyWalk));

        $adaptive = $this->getOutputFiles('adaptive');
        $this->assertEquals(1, count($adaptive));

        foreach ([$dailyWalk, $adaptive] as $outputFiles) {
            foreach ($outputFiles as $file) {
                /** @var $file SplFileInfo */
                $this->assertHeader($file->getPathname(), [
                    'id',
                    'idProfile',
                    'date',
                    'sourceMedium',
                    'landingPagePath',
                    'pageviews'
                ]);
            }
        }
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

    public function testAppCustomMetrics()
    {
        $this->config['action'] = 'customMetrics';
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

    public function testAppAuthException()
    {
        $this->expectException('Keboola\GoogleAnalyticsExtractor\Exception\UserException');
        $this->config = $this->getConfig();
        $this->config['authorization']['oauth_api']['credentials'] = [
            'appKey' => getenv('CLIENT_ID'),
            '#appSecret' => getenv('CLIENT_SECRET'),
            '#data' => json_encode([
                'access_token' => 'cowshit',
                'refresh_token' => 'bullcrap'
            ])
        ];
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

        $usersOutputFiles = $this->getOutputFiles('users');
        $usersManifestFiles = $this->getManifestFiles('users');

        $this->assertEmpty($usersOutputFiles);
        $this->assertEmpty($usersManifestFiles);
    }

    public function testSampleActionEmptyResult()
    {
        $this->config['action'] = 'sample';
        // set metric that will return no data
        unset($this->config['parameters']['queries'][1]);
        $this->config['parameters']['queries'][0]['query']['metrics'] = [
            ['expression' => 'ga:adxRevenue']
        ];
        $usersOutputFiles = $this->getOutputFiles('users');
        $usersManifestFiles = $this->getManifestFiles('users');

        $process = $this->runProcess();
        $output = json_decode($process->getOutput(), true);

        $this->assertEquals(0, $process->getExitCode());
        $this->assertEmpty($output['data']);
        $this->assertEquals('success', $output['status']);
        $this->assertEquals(0, $output['rowCount']);
        $this->assertEmpty($usersOutputFiles);
        $this->assertEmpty($usersManifestFiles);
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
        $process->setTimeout(180);
        $process->run();

        return $process;
    }

    private function getOutputFiles($queryName)
    {
        $finder = new Finder();

        return $finder->files()
            ->in(ROOT_PATH . '/tests/data/out/tables')
            ->name('/^' . $queryName . '.*\.csv$/i')
        ;
    }

    private function getManifestFiles($queryName)
    {
        $finder = new Finder();

        return $finder->files()
            ->in(ROOT_PATH . '/tests/data/out/tables')
            ->name('/^' . $queryName . '.*\.manifest$/i')
        ;
    }

    private function assertHeader($pathname, array $expected)
    {
        $csv = new CsvFile($pathname);
        $csv->next();
        $header = $csv->current();

        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $header[$key]);
        }

        // test that header is not elsewhere in the output file
        $csv->next();
        while ($row = $csv->current()) {
            foreach ($expected as $key => $value) {
                $this->assertNotEquals($value, $row[$key]);
            }

            $csv->next();
        }
    }
}
