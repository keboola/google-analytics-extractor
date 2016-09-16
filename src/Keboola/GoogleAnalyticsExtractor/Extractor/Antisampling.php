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
        $readCount = intval($report['samplesReadCounts'][0]) * 0.8;

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
        $cumulative = array_sum(
            array_map(function (Result $result) {
                return intval($result->getMetrics()['ga:sessions']);
            }, $report['data'])
        );

        $bucketSize = $cumulative % $readCount;

        // only first date range
        $dateRanges = $query['query']['dateRanges'][0];
        $dateRangeBuckets = [];
        $startDate = new \DateTime($dateRanges['startDate']);
        $endDate = new \DateTime($dateRanges['endDate']);

        while ($startDate->diff($endDate)->format('%r%a') > 0) {
            $startDateString = $startDate->format('Y-m-d');
            $startDate->modify("+{$bucketSize} Days");
            $endDateString = $startDate->format('Y-m-d');

            $dateRangeBuckets[] = [
                'startDate' => $startDateString,
                'endDate' => $endDateString
            ];

            $startDate->modify("+1 Day");
        }

        return $dateRangeBuckets;
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
                $this->paginator->getOutput()->writeReport($this->outputCsv, $report, $query['query']['viewId']);
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
                $this->paginator->getOutput()->writeReport($this->outputCsv, $report, $query['query']['viewId']);
                $isFirstRun = false;
            }
            $this->paginator->paginate($query, $report, $this->outputCsv);
        }
    }
}
