<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 08/09/16
 * Time: 12:41
 */

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

class AntisamplingTest extends ClientTest
{
    private function buildQuery()
    {
        return [
            'name' => 'users',
            'query' => [
                'viewId' => getenv('VIEW_ID'),
                'metrics' => [
                    ['expression' => 'ga:users'],
                    ['expression' => 'ga:newUsers'],
                    ['expression' => 'ga:bounces'],
                    ['expression' => 'ga:pageviews']
                ],
                'dimensions' => [
                    ['name' => 'ga:date'],
                    ['name' => 'ga:source'],
                    ['name' => 'ga:medium'],
                    ['name' => 'ga:pagePath']
                ],
                'dateRanges' => [[
                    'startDate' => date('Y-m-d', strtotime('-36 months')),
                    'endDate' => date('Y-m-d', strtotime('now'))
                ]]
            ]
        ];
    }

    public function testDailyWalk()
    {
        $query = $this->buildQuery();
        $query['antisampling'] = 'dailyWalk';
        $query['samplingLevel'] = 'SMALL';
        $report = $this->client->getBatch($query);

        $this->arrayHasKey($report['data']);
        $this->assertNotEmpty($report['data']);
        $this->arrayHasKey($report['query']);
        $this->assertNotEmpty($report['query']);
        $this->arrayHasKey($report['rowCount']);
    }

    public function testAdaptive()
    {
        $query = $this->buildQuery();
        $query['antisampling'] = 'adaptive';
        $query['samplingLevel'] = 'SMALL';
        $report = $this->client->getBatch($query);

        $this->arrayHasKey($report['data']);
        $this->assertNotEmpty($report['data']);
        $this->arrayHasKey($report['query']);
        $this->assertNotEmpty($report['query']);
        $this->arrayHasKey($report['rowCount']);
    }
}
