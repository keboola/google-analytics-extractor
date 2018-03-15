<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 08/09/16
 * Time: 12:41
 */

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use Keboola\GoogleAnalyticsExtractor\Extractor\Antisampling;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\Extractor\Paginator;
use Keboola\GoogleAnalyticsExtractor\Logger\Logger;
use Symfony\Component\Filesystem\Filesystem;

class AntisamplingTest extends ClientTest
{
    private function buildQuery($algorithm = 'dailyWalk')
    {
        return [
            'name' => 'users',
            'outputTable' => 'antisampling-test',
            'samplingLevel' => 'SMALL',
            'antisampling' => $algorithm,
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
        $this->dailyWalk($this->buildQuery());
    }

    public function testDailyWalkWithDateHour()
    {
        $query = $this->buildQuery();
        $query['query']['dimensions'] = [
            ['name' => 'ga:dateHour'],
            ['name' => 'ga:source'],
            ['name' => 'ga:medium'],
            ['name' => 'ga:landingPagePath']
        ];
        $this->dailyWalk($query);
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

    public function testGaDateError()
    {
        $this->expectException('Keboola\\GoogleAnalyticsExtractor\\Exception\\UserException');
        $this->expectExceptionMessage(
            'At least one of these dimensions must be set in order to use anti-sampling: ga:date | ga:dateHour | ga:dateHourMinute'
        );
        $query = $this->buildQuery();
        $query['antisampling'] = 'dailyWalk';
        $query['samplingLevel'] = 'SMALL';
        $profile = ['id' => getenv('VIEW_ID')];
        $query['query']['dimensions'] = [
            ['name' => 'ga:week'],
            ['name' => 'ga:source'],
            ['name' => 'ga:medium'],
            ['name' => 'ga:landingPagePath'],
            ['name' => 'ga:pagePath'],
        ];
        $query['query']['dateRanges'] = [[
            'startDate' => date('Y-m-d', strtotime('-4 days')),
            'endDate' => date('Y-m-d', strtotime('-1 day'))
        ]];

        $output = new Output('/tmp/ga-test', uniqid('in.c-ex-google-analytics-test'));
        $logger = new Logger('ex-google-analytics-test');
        $extractor = new Extractor($this->client, $output, $logger);
        $extractor->run([$query], [$profile]);
    }

    private function dailyWalk($query)
    {
        $fs = new Filesystem();
        if (!$fs->exists('/tmp/ga-test')) {
            $fs->mkdir('/tmp/ga-test');
        }
        $fs->remove('/tmp/ga-test/*');

        // Daily Walk
        $report = $this->client->getBatch($query);

        $output = new Output('/tmp/ga-test', uniqid('in.c-ex-google-analytics-test'));
        $paginator = new Paginator($output, $this->client);
        $outputCsv = $output->createReport($query);
        (new Antisampling($paginator, $outputCsv))->dailyWalk($query, $report);

        $dailyWalkOutputCsv = $outputCsv->getPathname();

        // Manual Daily Walk
        $dates = [
            date('Y-m-d', strtotime('-4 days')),
            date('Y-m-d', strtotime('-3 days')),
            date('Y-m-d', strtotime('-2 days')),
            date('Y-m-d', strtotime('-1 days'))
        ];

        $query2 = $query;
        $query2['outputTable'] = 'antisampling-expected';
        $output = new Output('/tmp/ga-test', uniqid('in.c-ex-google-analytics-test'));
        $paginator = new Paginator($output, $this->client);
        $outputCsv = $output->createReport($query2);

        foreach ($dates as $date) {
            $query2['query']['dateRanges'] = [[
                'startDate' => $date,
                'endDate' => $date
            ]];
            $rep = $this->client->getBatch($query2);
            $paginator->paginate($query2, $rep, $outputCsv);
        }

        $expectedOutputCsv = $outputCsv->getPathname();

        $this->assertFileEquals($expectedOutputCsv, $dailyWalkOutputCsv);
    }
}
