<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 07/09/16
 * Time: 14:45
 */

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;


class Antisampling
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    private function getDateRangeBuckets($reportRequest, $data)
    {
        $readCount = intval($data['samplesReadCounts'][0]) * 0.8;

        $sessionReportRequest = $reportRequest;
        $sessionReportRequest['metrics'] = [
            ['expression' => 'ga:sessions']
        ];
        $sessionReportRequest['dimensions'] = [
            ['name' => 'ga:date']
        ];

        // get all sessions from full date range
        $json = $this->client->request('POST', Client::DATA_URL, ['reportRequests' => [$sessionReportRequest]]);
        $data = $json['reports'][0]['data'];

        // cumulative sum of sessions
        $cumulative = array_sum(
            array_map(function ($row) {
                return $row['metrics'][0]['values'][0];
            }, $data['rows'])
        );

        $bucketSize = $cumulative % $readCount;

        $dateRanges = $reportRequest['dateRanges'][0];
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


    public function dailyWalk()
    {
        return function ($reportRequest, $reports) {
            $dateRanges = $reportRequest['dateRanges'][0];
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

                foreach ($json['reports'][0]['data']['rows'] as $row) {
                    $reportsDataRows[] = $row;
                }

                $startDate->modify("+1 Day");
            }

            $reports['reports'][0]['data']['rows'] = $reportsDataRows;
            return $reports;
        };
    }

    public function adaptive()
    {
        return function ($reportRequest, $reports) {
            $dateRangeBuckets = $this->getDateRangeBuckets($reportRequest, $reports);
            $reportsDataRows = [];
            foreach ($dateRangeBuckets as $dateRange) {
                $reportRequest['dateRanges'][0] = $dateRange;
                $json = $this->client->request('POST', Client::DATA_URL, ['reportRequests' => [$reportRequest]]);

                foreach ($json['reports'][0]['data']['rows'] as $row) {
                    $reportsDataRows[] = $row;
                }
            }

            $reports['reports'][0]['data']['rows'] = $reportsDataRows;
            return $reports;
        };
    }
}
