<?php
/**
 * RestApi.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.7.13
 */

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use Keboola\Google\ClientBundle\Google\RestApi as GoogleApi;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use Keboola\GoogleAnalyticsExtractor\Logger\Logger;

class Client
{
    const ACCOUNTS_URL = 'https://www.googleapis.com/analytics/v3/management/accounts';
    const REPORTS_URL = 'https://analyticsreporting.googleapis.com/v4/reports:batchGet';
    const MCF_URL = 'https://www.googleapis.com/analytics/v3/data/mcf';
    const SEGMENTS_URL = 'https://www.googleapis.com/analytics/v3/management/segments';
    const CUSTOM_METRICS_URL = 'https://www.googleapis.com/analytics/v3/management/accounts/%s/webproperties/%s/customMetrics';

    /** @var GoogleApi */
    protected $api;

    /** @var Logger */
    protected $logger;

    protected $apiCallsCount = 0;

    public function __construct(GoogleApi $api, Logger $logger)
    {
        $this->api = $api;
        $this->logger = $logger;
        // don't retry on 403 error
        $this->api->setBackoffCallback403(function () {
            return false;
        });
        $this->api->setDelayFn(Client::getDelayFn());
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

    public function getCustomMetrics($accountId, $webPropertyId)
    {
        $response = $this->api->request(sprintf(self::CUSTOM_METRICS_URL, $accountId, $webPropertyId));
        $body = json_decode($response->getBody()->getContents(), true);
        return $body['items'];
    }

    public function request($method, $url, $body = null, $query = null)
    {
        $this->apiCallsCount++;

        $options = !is_null($body) ? ['json' => $body] : ['query' => $query];

        $response = $this->api->request(
            $url,
            $method,
            ['Accept' => 'application/json'],
            $options
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getBatch($query)
    {
        return ($query['endpoint'] === 'mcf')
            ? $this->getMCFReports($query)
            : $this->getReports($query);
    }

    public function getReports($query)
    {
        $body = [
            'reportRequests' => $this->getReportRequest($query['query'])
        ];
        $this->logger->debug(sprintf("Sending Report request"), [
            'ga_profile' => $query['query']['viewId'],
            'request' => [
                'method' => 'POST',
                'url' => self::REPORTS_URL,
                'body' => $body
            ]
        ]);
        $reports = $this->request('POST', self::REPORTS_URL, $body);

        return $this->processResponse($reports, $query);
    }

    public function getMCFReports($query)
    {
        $metrics = array_map(function ($item) {
            return $item['expression'];
        }, $query['query']['metrics']);

        $dimensions = array_map(function ($item) {
            return $item['name'];
        }, $query['query']['dimensions']);

        $params = [
            'ids' => sprintf('ga:%s', $query['query']['viewId']),
            'start-date' => date('Y-m-d', strtotime($query['query']['dateRanges'][0]['startDate'])),
            'end-date' => date('Y-m-d', strtotime($query['query']['dateRanges'][0]['endDate'])),
            'metrics' => implode(',', $metrics),
            'dimensions' => implode(',', $dimensions),
            'samplingLevel' => 'HIGHER_PRECISION',
            'start-index' => 1,
            'max-results' => 5000
        ];

        if (!empty($query['query']['filtersExpression'])) {
            $params['filters'] = $query['query']['filtersExpression'];
        }

        $this->logger->debug(sprintf("Sending MCF request"), [
            'request' => [
                'ids' => $query['query']['viewId'],
                'method' => 'GET',
                'url' => self::MCF_URL,
                'params' => $params
            ]
        ]);
        $reports = $this->request('GET', self::MCF_URL, null, $params);

        return $this->processResponseMCF($reports, $query);
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
        $query['samplingLevel'] = empty($query['samplingLevel']) ? 'LARGE' : $query['samplingLevel'];

        return $query;
    }

    /**
     * Parse JSON response to array of Result rows
     * @param $response
     * @param $query
     * @return mixed
     * @internal param array $result json decoded response
     */
    private function processResponse($response, $query)
    {
        if (empty($response['reports'])) {
            return [];
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
            'rowCount' => isset($report['data']['rowCount']) ? $report['data']['rowCount'] : 0
        ];

        if (isset($report['data']['samplesReadCounts'])) {
            $processed['samplesReadCounts'] = $report['data']['samplesReadCounts'];
        }

        if (isset($report['data']['samplingSpaceSizes'])) {
            $processed['samplingSpaceSizes'] = $report['data']['samplingSpaceSizes'];
        }

        if (isset($report['nextPageToken'])) {
            $processed['nextPageToken'] = $report['nextPageToken'];
        }

        return $processed;
    }

    private function processResponseMCF($response, $query)
    {
        if (empty($response['rows'])) {
            return [];
        }
        $rows = $response['rows'];

        $dataSet = [];
        $columnHeaders = $response['columnHeaders'];

        foreach ($rows as $row) {
            $results = [];
            foreach ($columnHeaders as $key => $columnData) {
                $columnType = strtolower($columnData['columnType']);
                $dataType = strtolower($columnData['dataType']);
                $results[$columnType][$columnData['name']] = $this->parseMCFValue($row[$key], $dataType);
            }
            $dataSet[] = new Result($results['metric'], $results['dimension']);
        }

        $processed = [
            'data' => $dataSet,
            'query' => $query,
            'totals' => $response['totalsForAllResults'],
            'rowCount' => isset($response['totalResults']) ? $response['totalResults'] : 0
        ];

        if (isset($response['samplesReadCounts'])) {
            $processed['samplesReadCounts'] = $response['samplesReadCounts'];
        }

        if (isset($report['data']['samplingSpaceSizes'])) {
            $processed['samplingSpaceSizes'] = $response['samplingSpaceSizes'];
        }

        if (isset($report['nextPageToken'])) {
            $processed['nextPageToken'] = $response['nextPageToken'];
        }

        return $processed;
    }

    private function parseMCFValue($data, $dataType)
    {
        if ($dataType !== 'mcf_sequence') {
            return $data['primitiveValue'];
        }

        if (isset($data['conversionPathValue'])) {
            $path = [];
            foreach ($data['conversionPathValue'] as $node) {
                $path[] = $node['nodeValue'];
            }

            return implode('>', $path);
        }

        throw new ApplicationException(
            sprintf(
                'Error parsing MCF response rows: one of the keys "%s" is not supported',
                implode(',', array_keys($data))
            )
        );
    }

    public function getApiCallsCount()
    {
        return $this->apiCallsCount;
    }

    public static function getDelayFn($base = 5000)
    {
        return function ($retries) use ($base) {
            return (int) ($base * pow(2, $retries - 1) + rand(0, 500));
        };
    }
}
