<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 13/04/16
 * Time: 15:22
 */

namespace Keboola\GoogleAnalyticsExtractor\Test;

use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /** @var Client */
    private $client;

    public function setUp()
    {
        $this->client = new Client(
            new RestApi(
                getenv('CLIENT_ID'),
                getenv('CLIENT_SECRET'),
                getenv('ACCESS_TOKEN'),
                getenv('REFRESH_TOKEN')
            )
        );
    }

    public function testGetBatch()
    {
        $queries = [
            [
                'name' => 'users',
                'query' => [
                    'viewId' => getenv('VIEW_ID'),
                    'metrics' => [
                        ['expression' => 'ga:users'],
                        ['expression' => 'ga:pageviews']
                    ],
                    'dimensions' => [
                        ['name' => 'ga:date'],
                        ['name' => 'ga:source'],
                        ['name' => 'ga:medium'],
                    ],
                    'dateRanges' => [[
                        'startDate' => date('Y-m-d', strtotime('-12 months')),
                        'endDate' => date('Y-m-d', strtotime('now'))
                    ]]
                ]
            ],
            [
                'name' => 'sessions',
                'query' => [
                    'viewId' => getenv('VIEW_ID'),
                    'metrics' => [
                        ['expression' => 'ga:sessions'],
                        ['expression' => 'ga:bounces']
                    ],
                    'dimensions' => [
                        ['name' => 'ga:date'],
                        ['name' => 'ga:source'],
                        ['name' => 'ga:country']
                    ],
                    'dateRanges' => [[
                        'startDate' => date('Y-m-d', strtotime('-12 months')),
                        'endDate' => date('Y-m-d', strtotime('now'))
                    ]]
                ]
            ]
        ];

        $reports = [];
        foreach ($queries as $query) {
            $reports[] = $this->client->getBatch($query);
        }

        $this->assertNotEmpty($reports[0]['data']);
        $this->assertEquals('users', $reports[0]['query']['name']);
        $this->assertNotEmpty($reports[1]['data']);
        $this->assertEquals('sessions', $reports[1]['query']['name']);
    }
}
