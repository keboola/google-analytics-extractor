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
                    ['expression' => 'ga:pageviews'],
                ],
                'dimensions' => [
                    ['name' => 'ga:date'],
                    ['name' => 'ga:source'],
                    ['name' => 'ga:medium'],
                    ['name' => 'ga:landingPagePath']
                ],
                'dateRanges' => [[
                    'startDate' => date('Y-m-d', strtotime('-4 days')),
                    'endDate' => date('Y-m-d', strtotime('-1 day'))
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

        // pagination
        do {
            $this->arrayHasKey($report['data']);
            $this->assertNotEmpty($report['data']);
            $this->arrayHasKey($report['query']);
            $this->assertNotEmpty($report['query']);
            $this->arrayHasKey($report['rowCount']);

            $nextQuery = null;
            if (isset($report['nextPageToken'])) {
                $query['query']['pageToken'] = $report['nextPageToken'];
                $nextQuery = $query;
                $report = $this->client->getBatch($nextQuery);
            }
            $query = $nextQuery;
        } while ($nextQuery);
    }

    public function testAdaptive()
    {
        $query = $this->buildQuery();
        $query['antisampling'] = 'adaptive';
        $query['samplingLevel'] = 'SMALL';
        $report = $this->client->getBatch($query);

        // pagination
        do {
            $this->arrayHasKey($report['data']);
            $this->assertNotEmpty($report['data']);
            $this->arrayHasKey($report['query']);
            $this->assertNotEmpty($report['query']);
            $this->arrayHasKey($report['rowCount']);

            $nextQuery = null;
            if (isset($report['nextPageToken'])) {
                $query['query']['pageToken'] = $report['nextPageToken'];
                $nextQuery = $query;
                $report = $this->client->getBatch($nextQuery);
            }
            $query = $nextQuery;
        } while ($nextQuery);
    }
}
