<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor;

use Generator;
use JsonException;
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

    public function testAppRunDailyWalk(): void
    {
        $this->config = $this->getConfig('_antisampling');
        $this->runProcess();

        $dailyWalk = $this->getManifestFiles('dailyWalk');
        Assert::assertEquals(1, count($dailyWalk));

        foreach ($dailyWalk as $file) {
            /** @var $file SplFileInfo */
            $this->assertManifestContainsColumns($file->getPathname(), [
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

        $adaptive = $this->getManifestFiles('adaptive');
        Assert::assertEquals(1, count($adaptive));

        foreach ($adaptive as $file) {
            /** @var $file SplFileInfo */
            $this->assertManifestContainsColumns($file->getPathname(), [
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

        $funnelFiles = $this->getManifestFiles('funnel');
        Assert::assertEquals(1, count($funnelFiles));

        foreach ($funnelFiles as $file) {
            /** @var $file SplFileInfo */
            $this->assertManifestContainsColumns($file->getPathname(), [
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

    private function assertManifestContainsColumns(string $pathname, array $expected): void
    {
        $manifest = (array) json_decode(file_get_contents($pathname), true, 512, JSON_THROW_ON_ERROR);
        Assert::assertArrayHasKey('columns', $manifest);
        Assert::assertEquals($expected, $manifest['columns']);
    }

    public function appRunDataProvider(): Generator
    {
        yield 'configRow' => [
            '',
        ];
    }
}
