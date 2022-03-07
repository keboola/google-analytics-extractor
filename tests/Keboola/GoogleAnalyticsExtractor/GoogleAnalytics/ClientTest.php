<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\Config;
use Keboola\GoogleAnalyticsExtractor\Configuration\ConfigDefinition;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ClientTest extends TestCase
{
    /** @var Client */
    protected $client;

    public function setUp(): void
    {
        $this->client = new Client(
            new RestApi(
                (string) getenv('CLIENT_ID'),
                (string) getenv('CLIENT_SECRET'),
                (string) getenv('ACCESS_TOKEN'),
                (string) getenv('REFRESH_TOKEN')
            ),
            new NullLogger(),
            []
        );
    }

    public function testGetBatch(): void
    {
        $queries = [
            [
                'name' => 'users',
                'endpoint' => Client::REPORTS_URL,
                'query' => [
                    'viewId' => getenv('VIEW_ID'),
                    'metrics' => [
                        ['expression' => 'ga:users'],
                        ['expression' => 'ga:newUsers'],
                        ['expression' => 'ga:bounces'],
                        ['expression' => 'ga:pageviews'],
                    ],
                    'dimensions' => [
                        ['name' => 'ga:date'],
                        ['name' => 'ga:source'],
                        ['name' => 'ga:medium'],
                        ['name' => 'ga:pagePath'],
                    ],
                    'dateRanges' => [[
                        'startDate' => date('Y-m-d', strtotime('-36 months')),
                        'endDate' => date('Y-m-d', strtotime('now')),
                    ]],
                ],
            ],
            [
                'name' => 'sessions',
                'endpoint' => Client::REPORTS_URL,
                'query' => [
                    'viewId' => getenv('VIEW_ID'),
                    'metrics' => [
                        ['expression' => 'ga:sessions'],
                        ['expression' => 'ga:bounces'],
                    ],
                    'dimensions' => [
                        ['name' => 'ga:date'],
                        ['name' => 'ga:source'],
                        ['name' => 'ga:country'],
                        ['name' => 'ga:pagePath'],
                    ],
                    'dateRanges' => [[
                        'startDate' => date('Y-m-d', strtotime('-12 months')),
                        'endDate' => date('Y-m-d', strtotime('now')),
                    ]],
                ],
            ],
        ];

        $reports = [];
        foreach ($queries as $query) {
            $reports[] = $this->client->getBatch($query);
        }

        Assert::assertNotEmpty($reports[0]['data']);
        Assert::assertEquals('users', $reports[0]['query']['name']);
        Assert::assertNotEmpty($reports[1]['data']);
        Assert::assertEquals('sessions', $reports[1]['query']['name']);
    }
}
