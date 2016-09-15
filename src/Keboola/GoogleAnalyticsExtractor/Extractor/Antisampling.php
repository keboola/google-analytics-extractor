<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 07/09/16
 * Time: 14:45
 */

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;

class Antisampling
{
    private $paginator;

    private $client;

    public function __construct(Paginator $paginator)
    {
        $this->paginator = $paginator;
        $this->client = $paginator->getClient();
    }

    private function getDateRangeBuckets($query, $report)
    {
        $readCount = intval($report['samplesReadCounts'][0]) * 0.8;

        $sessionQuery = $query;
        $sessionQuery['metrics'] = [
            ['expression' => 'ga:sessions']
        ];
        $sessionQuery['dimensions'] = [
            ['name' => 'ga:date']
        ];
        unset($query['pageToken']);

        // get all sessions from full date range
        $report = $this->client->getBatch($query);
        $data = $report['data'];

        // cumulative sum of sessions
        $cumulative = array_sum(
            array_map(function ($row) {
                return $row['metrics'][0]['values'][0];
            }, $data['rows'])
        );

        $bucketSize = $cumulative % $readCount;

        // only first date range
        $dateRanges = $query['dateRanges'][0];
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
        unset($query['pageToken']);
        $dateRanges = $query['dateRanges'][0];
        $startDate = new \DateTime($dateRanges['startDate']);
        $endDate = new \DateTime($dateRanges['endDate']);

        $reportsDataRows = [];
        while ($startDate->diff($endDate)->format('%r%a') > 0) {
            $startDateString = $startDate->format('Y-m-d');
            $startDate->modify("+1 Day");
            $endDateString = $startDate->format('Y-m-d');

            $reportRequest['dateRanges'][0] = [
                'startDate' => $startDateString,
                'endDate' => $endDateString
            ];
            $json = $this->client->request('POST', Client::DATA_URL, ['reportRequests' => [$reportRequest]]);

            $data = $json['reports'][0]['data'];
            if (!empty($data['rows'])) {
                foreach ($json['reports'][0]['data']['rows'] as $row) {
                    $reportsDataRows[] = $row;
                }
            }

            $startDate->modify("+1 Day");
        }

        $report['data']['rows'] = $reportsDataRows;
        return $report;
    }

    public function adaptive($query, $report)
    {
        $dateRangeBuckets = $this->getDateRangeBuckets($query, $report['data']);
        $reportsDataRows = [];
        foreach ($dateRangeBuckets as $dateRange) {
            $reportRequest['dateRanges'][0] = $dateRange;
            $json = $this->client->request('POST', Client::DATA_URL, ['reportRequests' => [$reportRequest]]);
            $data = $json['reports'][0]['data'];
            if (!empty($data['rows'])) {
                foreach ($data['rows'] as $row) {
                    $reportsDataRows[] = $row;
                }
            }
        }

        $reports['reports'][0]['data']['rows'] = $reportsDataRows;
        return $reports;
    }
}
