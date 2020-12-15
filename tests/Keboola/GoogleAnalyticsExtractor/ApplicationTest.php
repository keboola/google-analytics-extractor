<?php

namespace Keboola\GoogleAnalyticsExtractor;

use Keboola\Csv\CsvFile;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    /** @var Application */
    private $application;

    private $config;

    private $dataDir;

    public function setUp()
    {
        $this->dataDir = __DIR__ . '/../../../tests/data';
        $this->config = $this->getConfig();
        $this->application = new Application($this->config);

        $filesystem = new Filesystem();
        $filesystem->remove($this->dataDir . '/out/tables');
        $filesystem->mkdir($this->dataDir . '/out/tables');
    }

    private function getConfig($suffix = '')
    {
        $config = json_decode(file_get_contents($this->dataDir . '/config' . $suffix . '.json'), true);
        $config['app_name'] = 'ex-google-analytics';
        $config['parameters']['data_dir'] = $this->dataDir;
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

        $manifests = $this->getManifestFiles('');

        $this->assertEquals(1, count($profiles));
        $this->assertEquals(1, count($profilesManifests));

        $this->assertEquals(1, count($users));
        $this->assertEquals(1, count($usersManifests));

        foreach ($profilesManifests as $profilesManifestFile) {
            $profilesManifest = json_decode(file_get_contents($profilesManifestFile), true);
            $this->assertEquals($this->config['parameters']['outputBucket'] . '.profiles', $profilesManifest['destination']);
        }

        foreach ($usersManifests as $usersManifestFile) {
            $usersManifest = json_decode(file_get_contents($usersManifestFile), true);
            $this->assertEquals($this->config['parameters']['outputBucket'] . '.users', $usersManifest['destination']);
        }

        foreach ($manifests as $manifestFile) {
            $manifest = json_decode(file_get_contents($manifestFile), true);
            $this->assertArrayHasKey('destination', $manifest);
            $this->assertArrayHasKey('incremental', $manifest);
            $this->assertTrue($manifest['incremental']);
            $this->assertArrayHasKey('primary_key', $manifest);
            $this->assertEquals('id', $manifest['primary_key'][0]);
        }

        $this->assertFileExists($this->dataDir . '/out/usage.json');
        $usage = json_decode(file_get_contents($this->dataDir . '/out/usage.json'), true);
        $this->assertArrayHasKey('metric', $usage[0]);
        $this->assertArrayHasKey('value', $usage[0]);
        $this->assertGreaterThan(0, $usage[0]['value']);
        $this->assertEquals('API Calls', $usage[0]['metric']);
    }

    public function testAppRunDailyWalk()
    {
        $this->config = $this->getConfig('_antisampling');
        $this->application = new Application($this->config);
        $this->application->run();

        $dailyWalk = $this->getOutputFiles('dailyWalk');
        $this->assertEquals(1, count($dailyWalk));

        foreach ($dailyWalk as $file) {
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

    public function testAppRunAdaptive()
    {
        $this->config = $this->getConfig('_antisampling_adaptive');
        $this->application = new Application($this->config);
        $this->application->run();

        $adaptive = $this->getOutputFiles('adaptive');
        $this->assertEquals(1, count($adaptive));

        foreach ($adaptive as $file) {
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

    public function testAppRunMCF()
    {
        $this->config = $this->getConfig('_mcf');

        $this->application = new Application($this->config);
        $this->application->run();

        $funnelFiles = $this->getOutputFiles('funnel');
        $this->assertEquals(1, count($funnelFiles));

        foreach ($funnelFiles as $file) {
            /** @var $file SplFileInfo */
            $this->assertHeader($file->getPathname(), [
                'id',
                'idProfile',
                'mcf:conversionDate',
                'mcf:sourcePath',
                'mcf:mediumPath',
                'mcf:sourceMedium',
                'mcf:totalConversions',
                'mcf:totalConversionValue',
                'mcf:assistedConversions',
            ]);
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
        $this->assertNotEmpty($result['data']);
        $this->assertArrayHasKey('rowCount', $result);
        $this->assertEquals('success', $result['status']);
    }

    public function testAppSampleMCF()
    {
        $this->config = $this->getConfig('_mcf');
        $this->config['parameters']['query']['dateRanges'][0]['startDate'] = '-4 months';
        $this->config['action'] = 'sample';

        $this->application = new Application($this->config);

        $result = $this->application->run();

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('viewId', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
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
        $this->config['parameters']['retriesCount'] = 0;
        // unset segment dimension to trigger API error
        unset($this->config['parameters']['query']['dimensions'][1]);
        $this->application = new Application($this->config);
        $this->application->run();
    }

    public function testAppAuthException()
    {
        $this->expectException('Keboola\GoogleAnalyticsExtractor\Exception\UserException');
        $this->config = $this->getConfig();
        $this->config['parameters']['retriesCount'] = 0;
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
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());
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
        $this->config['parameters']['query']['metrics'] = [
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
        $this->config['parameters']['query']['metrics'] = [
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
        $this->config['parameters']['query']['metrics'] = [
            ['expression' => 'ga:adxRevenue']
        ];
        $this->config['parameters']['query']['dateRanges'][0] = [
            'startDate' => '0 day',
            'endDate' => '0 day',
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

        file_put_contents($dataPath . '/config.json', json_encode($this->config));

        $process = new Process(sprintf('php run.php --data=%s', $dataPath));
        $process->setTimeout(900);
        $process->run();

        return $process;
    }

    private function getOutputFiles($queryName)
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
            ->name('/^' . $queryName . '.*\.csv$/i')
        ;
    }

    private function getManifestFiles($queryName)
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
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
