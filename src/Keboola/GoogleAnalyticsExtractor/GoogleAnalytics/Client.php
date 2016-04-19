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
     *   - filter - filter expression [OPTIONAL]
     *   - segment - segment ID [OPTIONAL]
     *   - dateRanges - array of Date ranges
     *   - sort - dimension to sort by
     * @return array
     */
	private function getReportRequest($query)
	{
		return [
			'viewId' => $query['viewId'],
            'dateRanges' => array_map(function ($range) {
                return [
                    'startDate' => $range['since'],
                    'endDate' => $range['until']
                ];
            }, $query['dateRanges']),
			'metrics' => array_map(function ($metric) {
				return ['expression' => $metric];
			}, $query['metrics']),
			'dimensions' => array_map(function ($dimension) {
                return ['name' => $dimension];
            }, $query['dimensions']),
			'filtersExpression' => isset($query['filter'])?$query['filter']:'',
//            'orderBys' => [isset($query['orderBy'])?$query['orderBy']:''],
            'segments' => [],
            'pivots' => [],
//            'cohortGroups' => [],
            'pageToken' => empty($query['pageToken'])?null:$query['pageToken'],
            'pageSize' => 5000,
            'includeEmptyRows' => true,
            'hideTotals' => false,
            'hideValueRanges' => true,
            'samplingLevel' => 'LARGE',
		];
	}

    /**
     * @param $queries
     *
     * array of arrays
     *   - viewId - profile / view ID,
     *   - metrics - array of metrics
     *   - dimensions - array of dimensions [OPTIONAL]
     *   - filter - filter expression [OPTIONAL]
     *   - segment - segment ID [OPTIONAL]
     *   - dateRanges - array of Date ranges
     *   - orderBy - dimension or metric to order by
     *
     * @return array
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
	public function getBatch($queries) {
        $queryNames = [];
		$body['reportRequests'] = [];
		foreach ($queries as $query) {
			$body['reportRequests'][] = $this->getReportRequest($query['query']);
            $queryNames[] = $query['name'];
		};

        $response = $this->api->request(
            self::DATA_URL,
            'POST',
            ['Accept' => 'application/json'],
            ['json' => $body]
        );

        return $this->processResponse(json_decode($response->getBody()->getContents(), true), $queryNames);
	}

    /**
     * Parse JSON response to array of Result rows
     * @param $response
     * @return array
     * @internal param array $result json decoded response
     */
	private function processResponse($response, $queryNames)
	{
        $processed = [];
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
                'queryName' => $queryNames[$reportKey],
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
