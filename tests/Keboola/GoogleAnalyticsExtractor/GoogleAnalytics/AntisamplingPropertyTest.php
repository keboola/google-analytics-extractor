<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use Keboola\Component\UserException;
use Keboola\GoogleAnalyticsExtractor\Extractor\Antisampling\AntisamplingProperty;
use Keboola\GoogleAnalyticsExtractor\Extractor\Extractor;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\Extractor\Paginator\PropertiesPaginator;
use PHPUnit\Framework\Assert;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class AntisamplingPropertyTest extends ClientTest
{
    private function buildQuery(string $algorithm = 'dailyWalk'): array
    {
        return [
            'name' => 'users',
            'outputTable' => 'antisampling-test',
            'antisampling' => $algorithm,
            'endpoint' => Client::REPORTS_URL,
            'query' => [
                'metrics' => [
                    [
                        'name' => 'pageviews',
                        'expression' => 'screenPageViews',
                    ],
                ],
                'dimensions' => [
                    ['name' => 'date'],
                    ['name' => 'source'],
                    ['name' => 'medium'],
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
            ['name' => 'dateHour'],
            ['name' => 'source'],
            ['name' => 'medium'],
        ];
        $this->dailyWalk($query);
    }

    public function testGaDateError(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            'At least one of these dimensions must be set in order to use anti-sampling:' .
            ' date | dateHour'
        );
        $query = $this->buildQuery();
        $query['antisampling'] = 'dailyWalk';
        $query['query']['dimensions'] = [
            ['name' => 'week'],
            ['name' => 'source'],
            ['name' => 'medium'],
            ['name' => 'pagePath'],
        ];
        $query['query']['dateRanges'] = [[
            'startDate' => date('Y-m-d', strtotime('-40 days')),
            'endDate' => date('Y-m-d', strtotime('-1 day')),
        ]];

        $output = new Output('/tmp/ga-test');
        $logger = new NullLogger();
        $extractor = new Extractor($this->client, $output, $logger);
        $extractor->runProperties($query, [$this->getProperty()]);
    }

    private function dailyWalk(array $query): void
    {
        $fs = new Filesystem();
        if (!$fs->exists('/tmp/ga-test')) {
            $fs->mkdir('/tmp/ga-test');
        }
        $fs->remove('/tmp/ga-test/*');

        $property = $this->getProperty();
        $output = new Output('/tmp/ga-test');
        $paginator = new PropertiesPaginator($output, $this->client);
        $paginator->setProperty($property);
        $outputCsv = $output->createReport($query);
        (new AntisamplingProperty($paginator, $outputCsv, $property))->dailyWalk($query);

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
        $paginator = new PropertiesPaginator($output, $this->client);
        $paginator->setProperty($property);
        $outputCsv = $output->createReport($query2);

        foreach ($dates as $date) {
            $query2['query']['dateRanges'] = [[
                'startDate' => $date,
                'endDate' => $date,
            ]];
            $rep = $this->client->getPropertyReport($query2, $property);
            $paginator->paginate($query2, $rep, $outputCsv);
        }

        $expectedOutputCsv = $outputCsv->getPathname();

        Assert::assertFileEquals($expectedOutputCsv, $dailyWalkOutputCsv);
    }

    private function getProperty(): array
    {
        return [
            'accountKey' => 'accounts/185283969',
            'accountName' => 'Keboola',
            'propertyKey' => 'properties/255885884',
            'propertyName' => 'users',
        ];
    }
}
