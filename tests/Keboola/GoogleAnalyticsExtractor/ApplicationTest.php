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

    public function appRunDataProvider(): Generator
    {
        yield 'configRow' => [
            '',
        ];
    }
}
