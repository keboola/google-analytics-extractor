<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor\Antisampling;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\Extractor\Paginator\IPaginator;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Result;

class AntisamplingProfile implements IAntisampling
{
    private IPaginator $paginator;

    private Client $client;

    private CsvFile $outputCsv;

    public function __construct(IPaginator $paginator, CsvFile $outputCsv)
    {
        $this->paginator = $paginator;
        $this->client = $paginator->getClient();
        $this->outputCsv = $outputCsv;
    }

    private function getDateRangeBuckets(array $query, array $report): array
    {
        $readCount = intval($report['samplesReadCounts'][0]) * 0.9;

        $sessionQuery = $query;
        $sessionQuery['query']['metrics'] = [
            ['expression' => 'ga:sessions'],
        ];
        $sessionQuery['query']['dimensions'] = [
            ['name' => 'ga:date'],
        ];
        unset($sessionQuery['query']['pageToken']);

        // get all sessions from full date range
        $report = $this->client->getBatch($sessionQuery);

        // cumulative sum of sessions
        $cumulative = $this->getRunningTotal(
            array_map(function (Result $result) {
                return intval($result->getMetrics()['ga:sessions']);
            }, $report['data'])
        );

        $dates = array_map(function (Result $result) {
            $date = $result->getDimensions()['ga:date'];
            return substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
        }, $report['data']);

        // divide date range to buckets
        $sampleBucket = array_map(function ($item) use ($readCount) {
            return floor($item / $readCount) + 1;
        }, $cumulative);

        $buckets = $this->split($dates, $sampleBucket);

        // get the new date ranges
        $dateRangeBuckets = [];
        foreach ($buckets as $bucket) {
            $dateRangeBuckets[] = $this->getDateRange($bucket);
        }

        return $dateRangeBuckets;
    }

    private function split(array $vector, array $factor): array
    {
        $buckets = [];
        foreach ($factor as $key => $bucketId) {
            $buckets[$bucketId][] = $vector[$key];
        }

        return $buckets;
    }

    private function getDateRange(array $dates): array
    {
        $startDate = array_shift($dates);
        $endDate = array_pop($dates);
        if ($endDate === null) {
            $endDate = $startDate;
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    private function getRunningTotal(array $array): array
    {
        $generator = function (array $array) {
            $total = 0;
            foreach ($array as $key => $value) {
                $total += $value;
                yield $key => $total;
            }
        };
        return iterator_to_array($generator($array));
    }

    public function dailyWalk(array $query): void
    {
        unset($query['query']['pageToken']);
        $dateRanges = $query['query']['dateRanges'][0];
        $startDate = new \DateTime($this->client->getStartDate($dateRanges['startDate']));
        $endDate = new \DateTime($dateRanges['endDate']);

        while ($startDate->diff($endDate)->format('%r%a') >= 0) {
            $startDateString = $startDate->format('Y-m-d');

            $query['query']['dateRanges'][0] = [
                'startDate' => $startDateString,
                'endDate' => $startDateString,
            ];

            $report = $this->client->getBatch($query);

            $this->writeReport($query, $report);

            $startDate->modify('+1 Day');
        }
    }

    public function adaptive(array $query, array $report): void
    {
        $dateRangeBuckets = $this->getDateRangeBuckets($query, $report);
        foreach ($dateRangeBuckets as $dateRange) {
            $query['query']['dateRanges'][0] = $dateRange;
            $report = $this->client->getBatch($query);
            $this->writeReport($query, $report);
        }
    }

    private function writeReport(array $query, array $report): void
    {
        $this->paginator->paginate($query, $report, $this->outputCsv);
    }
}
