<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 07/09/16
 * Time: 14:45
 */

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Result;

class Antisampling
{
    private $paginator;

    private $client;

    private $outputCsv;

    public function __construct(Paginator $paginator, CsvFile $outputCsv)
    {
        $this->paginator = $paginator;
        $this->client = $paginator->getClient();
        $this->outputCsv = $outputCsv;
    }

    private function getDateRangeBuckets($query, $report)
    {
        $readCount = intval($report['samplesReadCounts'][0]) * 0.9;

        $sessionQuery = $query;
        $sessionQuery['query']['metrics'] = [
            ['expression' => 'ga:sessions']
        ];
        $sessionQuery['query']['dimensions'] = [
            ['name' => 'ga:date']
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
            return floor($item/$readCount) + 1;
        }, $cumulative);

        $buckets = $this->split($dates, $sampleBucket);

        // get the new date ranges
        $dateRangeBuckets = [];
        foreach ($buckets as $bucket) {
            $dateRangeBuckets[] = $this->getDateRange($bucket);
        }

        return $dateRangeBuckets;
    }

    private function split($vector, $factor)
    {
        $buckets = [];
        foreach ($factor as $key => $bucketId) {
            $buckets[$bucketId][] = $vector[$key];
        }

        return $buckets;
    }

    private function getDateRange($dates)
    {
        $startDate = array_shift($dates);
        $endDate = array_pop($dates);
        if ($endDate == null) {
            $endDate = $startDate;
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
    }

    private function getRunningTotal(array $array)
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


    public function dailyWalk($query, $report)
    {
        unset($query['query']['pageToken']);
        $dateRanges = $query['query']['dateRanges'][0];
        $startDate = new \DateTime($dateRanges['startDate']);
        $endDate = new \DateTime($dateRanges['endDate']);

        $isFirstRun = true;
        while ($startDate->diff($endDate)->format('%r%a') > 0) {
            $startDateString = $startDate->format('Y-m-d');
            $startDate->modify("+1 Day");
            $endDateString = $startDate->format('Y-m-d');

            $query['query']['dateRanges'][0] = [
                'startDate' => $startDateString,
                'endDate' => $endDateString
            ];

            $report = $this->client->getBatch($query);

            if ($isFirstRun) {
                $this->paginator->getOutput()->writeReport($this->outputCsv, $report, $query['query']['viewId'], true);
                $isFirstRun = false;
            }

            $this->paginator->paginate($query, $report, $this->outputCsv);

            $startDate->modify("+1 Day");
        }
    }

    public function adaptive($query, $report)
    {
        $dateRangeBuckets = $this->getDateRangeBuckets($query, $report);
        $isFirstRun = true;
        foreach ($dateRangeBuckets as $dateRange) {
            $query['query']['dateRanges'][0] = $dateRange;
            $report = $this->client->getBatch($query);
            if ($isFirstRun) {
                $this->paginator->getOutput()->writeReport($this->outputCsv, $report, $query['query']['viewId'], true);
                $isFirstRun = false;
            }
            $this->paginator->paginate($query, $report, $this->outputCsv);
        }
    }
}
