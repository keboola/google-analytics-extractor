<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use Keboola\Component\UserException;
use Keboola\GoogleAnalyticsExtractor\Extractor\Antisampling\AntisamplingProfile;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\Extractor\Paginator\ProfilesPaginator;
use PHPUnit\Framework\Assert;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Filesystem\Filesystem;

class AntisamplingProfileTest extends ClientTest
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

    private function dailyWalk(array $query): void
    {
        $fs = new Filesystem();
        if (!$fs->exists('/tmp/ga-test')) {
            $fs->mkdir('/tmp/ga-test');
        }
        $fs->remove('/tmp/ga-test/*');
        $logger = new TestLogger();
        $output = new Output('/tmp/ga-test', 'outputBucket');
        $paginator = new ProfilesPaginator($output, $this->client, $logger);
        $outputCsv = $output->createReport($query);
        (new AntisamplingProfile($paginator, $outputCsv))->dailyWalk($query);

        $dailyWalkOutputCsv = $outputCsv->getPathname();

        // Manual Daily Walk
        $dates = [
            date('Y-m-d', strtotime('-4 days')),
            date('Y-m-d', strtotime('-3 days')),
            date('Y-m-d', strtotime('-2 days')),
            date('Y-m-d', strtotime('-1 days')),
        ];

        $logger = new TestLogger();
        $query2 = $query;
        $query2['outputTable'] = 'antisampling-expected';
        $output = new Output('/tmp/ga-test', 'outputBucket');
        $paginator = new ProfilesPaginator($output, $this->client, $logger);
        $outputCsv = $output->createReport($query2);

        foreach ($dates as $date) {
            $query2['query']['dateRanges'] = [[
                'startDate' => $date,
                'endDate' => $date,
            ]];
            $rep = $this->client->getBatch($query2);
            $paginator->paginate($query2, $rep, $outputCsv);
        }
        Assert::assertCount(count($dates), $logger->records);

        $expectedOutputCsv = $outputCsv->getPathname();

        Assert::assertFileEquals($expectedOutputCsv, $dailyWalkOutputCsv);
    }
}
