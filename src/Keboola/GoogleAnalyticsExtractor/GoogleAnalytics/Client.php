<?php
/**
 * RestApi.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.7.13
 */

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use Keboola\Google\ClientBundle\Google\RestApi as GoogleApi;

class Client
{
    const ACCOUNTS_URL = 'https://www.googleapis.com/analytics/v3/management/accounts';
    const DATA_URL = 'https://analyticsreporting.googleapis.com/v4/reports:batchGet';
    const SEGMENTS_URL = 'https://www.googleapis.com/analytics/v3/management/segments';

    /** @var GoogleApi */
    protected $api;

    public function __construct(GoogleApi $api)
    {
        $this->api = $api;
    }

    /**
     * @return GoogleApi
     */
    public function getApi()
    {
        return $this->api;
    }

    public function getSegments()
    {
        $response = $this->api->request(self::SEGMENTS_URL);
        $body = json_decode($response->getBody()->getContents(), true);
        return $body['items'];
    }

    /**
     * @param $query
     *
     * array of arrays
     *   - viewId - profile / view ID,
     *   - metrics - array of metrics
     *   - dimensions - array of dimensions [OPTIONAL]
     *   - filtersExpression - filter expression [OPTIONAL]
     *   - segments - segment ID [OPTIONAL]
     *   - dateRanges - array of Date ranges
     *   - orderBy - dimension or metric to order by
     *
     * @return array
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function getBatch($query)
    {
        $body['reportRequests'][] = $this->getReportRequest($query['query']);

        $response = $this->api->request(
            self::DATA_URL,
            'POST',
            ['Accept' => 'application/json'],
            ['json' => $body]
        );

        return $this->processResponse(json_decode($response->getBody()->getContents(), true), $query);
    }

    /**
     * Format query array to ReportRequest
     *
     * @param $query
     *   - viewId - profile / view ID,
     *   - metrics - array of metrics
     *   - dimensions - array of dimensions [OPTIONAL]
     *   - filtersExpression - filter expression [OPTIONAL]
     *   - segments - segment ID [OPTIONAL]
     *   - dateRanges - array of Date ranges
     *   - sort - dimension to sort by
     * @return array
     */
    private function getReportRequest($query)
    {
        $query['dateRanges'] = array_map(function ($item) {
            return [
                'startDate' => date('Y-m-d', strtotime($item['startDate'])),
                'endDate' => date('Y-m-d', strtotime($item['endDate']))
            ];
        }, $query['dateRanges']);
        $query['pageSize'] = 5000;
        $query['includeEmptyRows'] = true;
        $query['hideTotals'] = false;
        $query['hideValueRanges'] = true;
        $query['samplingLevel'] = 'LARGE';

        return $query;
    }

    /**
     * Parse JSON response to array of Result rows
     * @param $response
     * @param $query
     * @return array
     * @internal param array $result json decoded response
     */
    private function processResponse($response, $query)
    {
        if (empty($response['reports'])) {
            return null;
        }
        $report = $response['reports'][0];

        $dataSet = [];
        $dimensions = [];
        $metrics = [];
        $dimensionNames = $report['columnHeader']['dimensions'];
        $metricNames = array_map(function ($metric) {
            return $metric['name'];
        }, $report['columnHeader']['metricHeader']['metricHeaderEntries']);

        if (!empty($report['data']['rows'])) {
            foreach ($report['data']['rows'] as $row) {
                foreach ($row['dimensions'] as $k => $v) {
                    $dimensions[$dimensionNames[$k]] = $v;
                }
                foreach ($row['metrics'][0]['values'] as $k => $v) {
                    $metrics[$metricNames[$k]] = $v;
                }
                $dataSet[] = new Result($metrics, $dimensions);
            }
        }

        $processed = [
            'data' => $dataSet,
            'query' => $query,
            'totals' => $report['data']['totals'],
            'rowCount' => $report['data']['rowCount']
        ];

        if (isset($report['nextPageToken'])) {
            $processed['nextPageToken'] = $report['nextPageToken'];
        }

        return $processed;
    }
}
