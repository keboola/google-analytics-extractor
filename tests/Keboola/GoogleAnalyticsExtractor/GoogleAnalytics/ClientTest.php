<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use GuzzleHttp\Exception\ClientException;
use Keboola\Google\ClientBundle\Google\RestApi;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ClientTest extends TestCase
{
    protected Client $client;

    private Logger $logger;

    public function setUp(): void
    {
        $testHandler = new TestHandler();
        $this->logger = new Logger('Google Analytics API tests');
        $this->logger->pushHandler($testHandler);

        $this->client = new Client(
            RestApi::createWithOAuth(
                (string) getenv('CLIENT_ID'),
                (string) getenv('CLIENT_SECRET'),
                (string) getenv('ACCESS_TOKEN'),
                (string) getenv('REFRESH_TOKEN'),
                $this->logger,
            ),
            new NullLogger(),
            [],
        );
    }

    public function testUnknownMetric(): void
    {
        $query = [
            'query' => [
                'metrics' => [
                    ['name' => 'metric2'],
                    ['name' => 'metric1'],
                    ['name' => 'goal11Completions'],
                ],
                'dimensions' => [
                    ['name' => 'date'],
                    ['name' => 'source'],
                    ['name' => 'country'],
                    ['name' => 'pagePath'],
                ],
                'dateRanges' => [[
                    'startDate' => date('Y-m-d', strtotime('-12 months')),
                    'endDate' => date('Y-m-d', strtotime('now')),
                ]],
            ],
        ];

        $this->client->getApi()->setBackoffsCount(3);

        $exceptionCaught = false;

        try {
            $this->client->getPropertyReport($query, ['propertyKey' => 'properties/255885884']);
        } catch (ClientException $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('400 Bad Request', $e->getMessage());
            $this->assertStringContainsString(
                'Did you mean sessions? Field metric2 is not a valid metric.',
                $e->getMessage(),
            );
        }

        $this->assertTrue($exceptionCaught, 'Expected ClientException was not thrown.');
    }
}
