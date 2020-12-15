<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use Keboola\Component\UserException;
use Keboola\GoogleAnalyticsExtractor\Extractor\Antisampling;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\Extractor\Paginator;
use PHPUnit\Framework\Assert;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class AntisamplingTest extends ClientTest
{
    private function buildQuery(string $algorithm = 'dailyWalk'): array
    {
        return [
            'name' => 'users',
            'outputTable' => 'antisampling-test',
            'samplingLevel' => 'SMALL',
            'antisampling' => $algorithm,
            'endpoint' => Client::REPORTS_URL,
            'query' => [
                'viewId' => getenv('VIEW_ID'),
                'metrics' => [
                    ['expression' => 'ga:pageviews'],
                ],
                'dimensions' => [
                    ['name' => 'ga:date'],
                    ['name' => 'ga:source'],
                    ['name' => 'ga:medium'],
                    ['name' => 'ga:landingPagePath'],
                ],
                'dateRanges' => [[
                    'startDate' => date('Y-m-d', strtotime('-4 days')),
                    'endDate' => date('Y-m-d', strtotime('-1 day')),
                ]],
            ],
        ];
    }

    public function testDailyWalk(): void
    {
        $this->dailyWalk($this->buildQuery());
    }

    public function testDailyWalkWithDateHour(): void
    {
        $query = $this->buildQuery();
        $query['query']['dimensions'] = [
            ['name' => 'ga:dateHour'],
            ['name' => 'ga:source'],
            ['name' => 'ga:medium'],
            ['name' => 'ga:landingPagePath'],
        ];
        $this->dailyWalk($query);
    }

    public function testAdaptive(): void
    {
        $query = $this->buildQuery();
        $query['antisampling'] = 'adaptive';
        $query['samplingLevel'] = 'SMALL';
        $report = $this->client->getBatch($query);

        Assert::arrayHasKey($report['data']);
        Assert::assertNotEmpty($report['data']);
        Assert::arrayHasKey($report['query']);
        Assert::assertNotEmpty($report['query']);
        Assert::arrayHasKey($report['rowCount']);
    }

    public function testGaDateError(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            'At least one of these dimensions must be set in order to use anti-sampling:' .
            ' ga:date | ga:dateHour | ga:dateHourMinute'
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
            'endDate' => date('Y-m-d', strtotime('-1 day')),
        ]];

        $output = new Output('/tmp/ga-test');
        $logger = new NullLogger();
        $extractor = new Extractor($this->client, $output, $logger);
        $extractor->run($query, [$profile]);
    }

    private function dailyWalk(array $query): void
    {
        $fs = new Filesystem();
        if (!$fs->exists('/tmp/ga-test')) {
            $fs->mkdir('/tmp/ga-test');
        }
        $fs->remove('/tmp/ga-test/*');

        $output = new Output('/tmp/ga-test');
        $paginator = new Paginator($output, $this->client);
        $outputCsv = $output->createReport($query);
        (new Antisampling($paginator, $outputCsv))->dailyWalk($query);

        $dailyWalkOutputCsv = $outputCsv->getPathname();

        // Manual Daily Walk
        $dates = [
            date('Y-m-d', strtotime('-4 days')),
            date('Y-m-d', strtotime('-3 days')),
            date('Y-m-d', strtotime('-2 days')),
            date('Y-m-d', strtotime('-1 days')),
        ];

        $query2 = $query;
        $query2['outputTable'] = 'antisampling-expected';
        $output = new Output('/tmp/ga-test');
        $paginator = new Paginator($output, $this->client);
        $outputCsv = $output->createReport($query2);

        foreach ($dates as $date) {
            $query2['query']['dateRanges'] = [[
                'startDate' => $date,
                'endDate' => $date,
            ]];
            $rep = $this->client->getBatch($query2);
            $paginator->paginate($query2, $rep, $outputCsv);
        }

        $expectedOutputCsv = $outputCsv->getPathname();

        Assert::assertFileEquals($expectedOutputCsv, $dailyWalkOutputCsv);
    }
}
