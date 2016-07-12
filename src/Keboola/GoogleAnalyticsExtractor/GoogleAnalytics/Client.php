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
	/** @var GoogleApi */
	protected $api;

	const ACCOUNTS_URL = 'https://www.googleapis.com/analytics/v3/management/accounts';
	const DATA_URL = 'https://analyticsreporting.googleapis.com/v4/reports:batchGet';

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
     * @param $queries
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
	public function getBatch($queries) {
		$body['reportRequests'] = [];
		foreach ($queries as $query) {
			$body['reportRequests'][] = $this->getReportRequest($query['query']);
		};

        $response = $this->api->request(
            self::DATA_URL,
            'POST',
            ['Accept' => 'application/json'],
            ['json' => $body]
        );

        return $this->processResponse(json_decode($response->getBody()->getContents(), true), $queries);
	}

    /**
     * Parse JSON response to array of Result rows
     * @param $response
     * @return array
     * @internal param array $result json decoded response
     */
	private function processResponse($response, $queries)
	{
        $processed = [];
        if (empty($response['reports'])) {
            return ['reports' => []];
        }
        foreach ($response['reports'] as $reportKey => $report) {
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

            $processed['reports'][$reportKey] = [
                'data' => $dataSet,
                'query' => $queries[$reportKey],
                'totals' => $report['data']['totals'],
                'rowCount' => $report['data']['rowCount']
            ];

            if (isset($report['nextPageToken'])) {
                $processed['reports'][$reportKey]['nextPageToken'] = $report['nextPageToken'];
            }
        }

        return $processed;
	}
}
