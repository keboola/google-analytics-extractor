<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor;

use Generator;
use Keboola\Csv\CsvFile;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

class ApplicationTest extends TestCase
{
    private array $config;

    private string $dataDir;

    private string $configDir;

    public function setUp(): void
    {
        $this->dataDir = '/tmp/data-test';
        $this->configDir = __DIR__ . '/../../../tests/data';
        $this->config = $this->getConfig();

        $filesystem = new Filesystem();
        $filesystem->remove($this->dataDir . '/out/tables');
        $filesystem->mkdir($this->dataDir . '/out/tables');
    }

    private function getConfig(string $suffix = ''): array
    {
        $config = json_decode(
            (string) file_get_contents($this->configDir . '/config' . $suffix . '.json'),
            true,
        );
        $config['authorization']['oauth_api']['credentials'] = [
            'appKey' => getenv('CLIENT_ID'),
            '#appSecret' => getenv('CLIENT_SECRET'),
            '#data' => json_encode([
                'access_token' => getenv('ACCESS_TOKEN'),
                'refresh_token' => getenv('REFRESH_TOKEN'),
            ]),
        ];

        return $config;
    }

    /**
     * @dataProvider appRunDataProvider
     */
    public function testAppRun(string $configSuffix): void
    {
        $this->config = $this->getConfig($configSuffix);
        $this->runProcess();

        $profiles = $this->getOutputFiles('profiles');
        $profilesManifests = $this->getManifestFiles('profiles');

        $users = $this->getOutputFiles('users');
        $usersManifests = $this->getManifestFiles('users');

        $disabledUsers = $this->getOutputFiles('disabledUsers');

        $manifests = $this->getManifestFiles('');

        Assert::assertEquals(1, count($profiles));
        Assert::assertEquals(1, count($profilesManifests));

        Assert::assertEquals(1, count($users));
        Assert::assertEquals(1, count($usersManifests));

        Assert::assertEquals(0, count($disabledUsers));

        foreach ($manifests as $manifestFile) {
            $manifest = json_decode((string) file_get_contents((string) $manifestFile), true);
            Assert::assertArrayHasKey('incremental', $manifest);
            Assert::assertTrue($manifest['incremental']);
            Assert::assertArrayHasKey('primary_key', $manifest);
            Assert::assertEquals('id', $manifest['primary_key'][0]);
        }

        Assert::assertFileExists($this->dataDir . '/out/usage.json');
        $usage = json_decode((string) file_get_contents($this->dataDir . '/out/usage.json'), true);
        Assert::assertArrayHasKey('metric', $usage[0]);
        Assert::assertArrayHasKey('value', $usage[0]);
        Assert::assertGreaterThan(0, $usage[0]['value']);
        Assert::assertEquals('API Calls', $usage[0]['metric']);
    }

    public function testAppRunDailyWalk(): void
    {
        $this->config = $this->getConfig('_antisampling');
        $this->runProcess();

        $dailyWalk = $this->getOutputFiles('dailyWalk');
        Assert::assertEquals(1, count($dailyWalk));

        foreach ($dailyWalk as $file) {
            /** @var $file SplFileInfo */
            $this->assertHeader($file->getPathname(), [
                'id',
                'idProfile',
                'date',
                'sourceMedium',
                'landingPagePath',
                'pageviews',
            ]);
        }
    }

    public function testAppRunAdaptive(): void
    {
        $this->config = $this->getConfig('_antisampling_adaptive');
        $this->runProcess();

        $adaptive = $this->getOutputFiles('adaptive');
        Assert::assertEquals(1, count($adaptive));

        foreach ($adaptive as $file) {
            /** @var $file SplFileInfo */
            $this->assertHeader($file->getPathname(), [
                'id',
                'idProfile',
                'date',
                'sourceMedium',
                'landingPagePath',
                'pageviews',
            ]);
        }
    }

    public function testAppRunMCF(): void
    {
        $this->config = $this->getConfig('_mcf');
        $this->runProcess();

        $funnelFiles = $this->getOutputFiles('funnel');
        Assert::assertEquals(1, count($funnelFiles));

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

    public function testAppSegments(): void
    {
        $this->config['action'] = 'segments';
        $result = json_decode($this->runProcess()->getOutput(), true);

        Assert::assertArrayHasKey('status', $result);
        Assert::assertArrayHasKey('data', $result);
        Assert::assertEquals('success', $result['status']);
    }

    public function testAppProfilesProperties(): void
    {
        $this->config = $this->getConfig('_empty');
        $this->config['action'] = 'getProfilesProperties';
        $result = json_decode($this->runProcess()->getOutput(), true);

        Assert::assertArrayHasKey('profiles', $result);
        Assert::assertArrayHasKey('properties', $result);
    }

    public function testAppCustomMetrics(): void
    {
        $this->config['action'] = 'customMetrics';
        $result = (array) json_decode(
            $this->runProcess()->getOutput(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        Assert::assertArrayHasKey('status', $result);
        Assert::assertArrayHasKey('data', $result);
        Assert::assertEquals('success', $result['status']);
    }

    public function testAppRunProperties(): void
    {
        $this->config = $this->getConfig('_properties');
        $this->runProcess();

        $properties = $this->getOutputFiles('properties');
        $propertiesManifests = $this->getManifestFiles('properties');

        $users = $this->getOutputFiles('users');
        $usersManifests = $this->getManifestFiles('users');

        Assert::assertEquals(1, count($properties));
        Assert::assertEquals(1, count($propertiesManifests));

        Assert::assertEquals(1, count($users));
        Assert::assertEquals(1, count($usersManifests));
    }

    public function testAppUserException(): void
    {
        $this->config = $this->getConfig();
        $this->config['parameters']['retriesCount'] = 0;
        // unset segment dimension to trigger API error
        unset($this->config['parameters']['query']['dimensions'][1]);
        $errorOutput = $this->runProcess()->getErrorOutput();
        Assert::assertStringContainsString('Expired or wrong credentials, please reauthorize.', $errorOutput);
    }

    public function testAppAuthException(): void
    {
        $this->config = $this->getConfig();
        $this->config['parameters']['retriesCount'] = 0;
        $this->config['authorization']['oauth_api']['credentials'] = [
            'appKey' => getenv('CLIENT_ID'),
            '#appSecret' => getenv('CLIENT_SECRET'),
            '#data' => json_encode([
                'access_token' => 'cowshit',
                'refresh_token' => 'bullcrap',
            ]),
        ];
        $errorOutput = $this->runProcess()->getErrorOutput();
        Assert::assertStringContainsString('Expired or wrong credentials, please reauthorize.', $errorOutput);
    }

    public function testRunSegmentsAction(): void
    {
        $this->config['action'] = 'segments';
        $process = $this->runProcess();
        Assert::assertEquals(0, $process->getExitCode());

        $output = json_decode($process->getOutput(), true);
        Assert::assertArrayHasKey('status', $output);
        Assert::assertArrayHasKey('data', $output);
        Assert::assertEquals('success', $output['status']);
        Assert::assertNotEmpty($output['data']);
        $segment = $output['data'][0];
        Assert::assertArrayHasKey('id', $segment);
        Assert::assertArrayHasKey('kind', $segment);
        Assert::assertArrayHasKey('segmentId', $segment);
        Assert::assertArrayHasKey('name', $segment);
    }

    public function testActionUserException(): void
    {
        $this->config['action'] = 'sample';
        $this->config['parameters']['query']['metrics'] = [
            ['expression' => 'ga:nonexistingmetric'],
        ];
        $process = $this->runProcess();

        Assert::assertEquals(1, $process->getExitCode());
        Assert::assertStringContainsString(
            'Unknown metric(s): ga:nonexistingmetric',
            $process->getErrorOutput(),
        );
    }

    public function testRunEmptyResult(): void
    {
        // set metric that will return no data
        $this->config['parameters']['query']['metrics'] = [
            ['expression' => 'ga:adxRevenue'],
        ];
        $this->config['parameters']['query']['dateRanges'][0] = [
            'startDate' => '0 day',
            'endDate' => '0 day',
        ];
        $process = $this->runProcess();
        Assert::assertEquals(0, $process->getExitCode());

        $usersOutputFiles = $this->getOutputFiles('users');
        $usersManifestFiles = $this->getManifestFiles('users');

        Assert::assertCount(1, $usersOutputFiles);
        Assert::assertCount(1, $usersManifestFiles);

        /** @var \SplFileInfo $usersOutputFile */
        foreach ($usersOutputFiles as $usersOutputFile) {
            $file = new CsvFile((string) $usersOutputFile->getRealPath());
            $file->rewind();
            $rowCount = 0;
            while ($file->current()) {
                $rowCount++;
                $file->next();
            }
            Assert::assertEquals(1, $rowCount);
        }

        Assert::assertCount(1, $usersOutputFiles);
        Assert::assertCount(1, $usersManifestFiles);
    }

    public function testSampleActionEmptyResult(): void
    {
        $this->config['action'] = 'sample';
        // set metric that will return no data
        $this->config['parameters']['query']['metrics'] = [
            ['expression' => 'ga:adxRevenue'],
        ];
        $this->config['parameters']['query']['dateRanges'][0] = [
            'startDate' => '0 day',
            'endDate' => '0 day',
        ];

        $usersOutputFiles = $this->getOutputFiles('users');
        $usersManifestFiles = $this->getManifestFiles('users');

        $process = $this->runProcess();
        $output = (array) json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        Assert::assertEquals(0, $process->getExitCode());
        Assert::assertEmpty($output['data']);
        Assert::assertEquals('success', $output['status']);
        Assert::assertEquals(0, $output['rowCount']);
        Assert::assertEmpty($usersOutputFiles);
        Assert::assertEmpty($usersManifestFiles);
    }

    private function runProcess(): Process
    {
        $fs = new Filesystem();
        $fs->remove($this->dataDir);
        $fs->mkdir($this->dataDir);
        $fs->mkdir($this->dataDir . '/out/tables');

        file_put_contents($this->dataDir . '/config.json', json_encode($this->config));

        $process = new Process(['php', 'src/run.php']);
        $process->setEnv([
            'KBC_DATADIR' => $this->dataDir,
        ]);
        $process->setTimeout(900);
        $process->run();

        return $process;
    }

    private function getOutputFiles(string $queryName): Finder
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
            ->name('/^' . $queryName . '.*\.csv$/i')
        ;
    }

    private function getManifestFiles(string $queryName): Finder
    {
        $finder = new Finder();

        return $finder->files()
            ->in($this->dataDir . '/out/tables')
            ->name('/^' . $queryName . '.*\.csv.manifest$/i')
        ;
    }

    private function assertHeader(string $pathname, array $expected): void
    {
        $csv = new CsvFile($pathname);
        $csv->next();
        $header = $csv->current();

        foreach ($expected as $key => $value) {
            Assert::assertEquals($value, $header[$key]);
        }

        // test that header is not elsewhere in the output file
        $csv->next();
        while ($row = $csv->current()) {
            foreach ($expected as $key => $value) {
                Assert::assertNotEquals($value, $row[$key]);
            }

            $csv->next();
        }
    }

    public function appRunDataProvider(): Generator
    {
        yield 'configRow' => [
            '',
        ];
    }
}
