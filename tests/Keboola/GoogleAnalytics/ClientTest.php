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
        $this->client = new Client(new RestApi(
            getenv('CLIENT_ID'),
            getenv('CLIENT_SECRET'),
            getenv('ACCESS_TOKEN'),
            getenv('REFRESH_TOKEN')
        ));
    }

    public function testGetBatch()
    {
        $queries = [
            [
                'name' => 'users',
                'viewId' => getenv('VIEW_ID'),
                'metrics' => ['ga:users','ga:pageviews'],
                'dimensions' => ['ga:date','ga:source','ga:medium'],
                'dateRanges' => [[
                    'since' => date('Y-m-d', strtotime('-3 days')),
                    'until' => date('Y-m-d', strtotime('now'))
                ]],
            ],
            [
                'name' => 'sessions',
                'viewId' => getenv('VIEW_ID'),
                'metrics' => ['ga:sessions','ga:bounces'],
                'dimensions' => ['ga:date','ga:country'],
                'dateRanges' => [[
                    'since' => date('Y-m-d', strtotime('-3 days')),
                    'until' => date('Y-m-d', strtotime('now'))
                ]],
            ]
        ];

        $reports = $this->client->getBatch($queries);

        $this->assertNotEmpty($reports['reports'][0]['data']);
        $this->assertEquals('users', $reports['reports'][0]['queryName']);
        $this->assertNotEmpty($reports['reports'][1]['data']);
        $this->assertEquals('sessions', $reports['reports'][1]['queryName']);
    }
}
