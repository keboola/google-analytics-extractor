<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use Keboola\Google\ClientBundle\Google\RestApi as GoogleApi;
use Keboola\GoogleAnalyticsExtractor\Configuration\Config;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use Psr\Log\LoggerInterface;

class Client
{
    /** @phpcs:disable */
    public const ACCOUNT_PROPERTIES_URL = 'https://analyticsadmin.googleapis.com/v1alpha/accountSummaries';
    public const ACCOUNT_WEB_PROPERTIES_URL = 'https://www.googleapis.com/analytics/v3/management/accounts/~all/webproperties/';
    public const ACCOUNT_PROFILES_URL = 'https://www.googleapis.com/analytics/v3/management/accounts/~all/webproperties/~all/profiles';
    public const ACCOUNTS_URL = 'https://www.googleapis.com/analytics/v3/management/accounts';
    public const REPORTS_URL = 'https://analyticsreporting.googleapis.com/v4/reports:batchGet';
    public const REPORTS_PROPERTIES_URL = 'https://analyticsdata.googleapis.com/v1beta/%s:runReport';
    public const PAGE_SIZE = 5;
    private const MCF_URL = 'https://www.googleapis.com/analytics/v3/data/mcf';
    private const SEGMENTS_URL = 'https://www.googleapis.com/analytics/v3/management/segments';
    private const CUSTOM_METRICS_URL = 'https://www.googleapis.com/analytics/v3/management/accounts/%s/webproperties/%s/customMetrics';
    /** @phpcs:enable */

    protected GoogleApi $api;

    protected LoggerInterface $logger;

    protected int $apiCallsCount = 0;

    private array $inputState;

    public function __construct(GoogleApi $api, LoggerInterface $logger, array $inputState)
    {
        $this->api = $api;
        $this->logger = $logger;
        $this->inputState = $inputState;
        // don't retry on 403 error
        $this->api->setBackoffCallback403(function () {
            return false;
        });
        $this->api->setDelayFn(Client::getDelayFn());
    }

    public function getApi(): GoogleApi
    {
        return $this->api;
    }

    public function getSegments(): array
    {
        $response = $this->api->request(self::SEGMENTS_URL);
        $body = json_decode($response->getBody()->getContents(), true);
        return $body['items'] ?? [];
    }

    public function getAccountProperties(int $pageSize = self::PAGE_SIZE): array
    {
        $accountSummaries = [];

        do {
            $url = sprintf('%s?pageSize=%d', self::ACCOUNT_PROPERTIES_URL, $pageSize);
            if (isset($body['nextPageToken'])) {
                $url = sprintf('%s&pageToken=%s', $url, $body['nextPageToken']);
            }
            $response = $response->getBody()->getContents();
            return $body;
            $body = json_decode($response->getBody()->getContents(), true);
            if (isset($body['accountSummaries'])) {
                $accountSummaries[] = $body['accountSummaries'];
            }
        } while (isset($body['nextPageToken']));

        return array_filter(array_merge([], ...$accountSummaries), fn(array $v) => isset($v['propertySummaries']));
    }

    public function getAccounts(): array
    {
        $response = $this->api->request(self::ACCOUNTS_URL);
        $body = json_decode($response->getBody()->getContents(), true);
        return $body['items'] ?? [];
    }

    public function getWebProperties(): array
    {
        $response = $this->api->request(self::ACCOUNT_WEB_PROPERTIES_URL);
        $body = json_decode($response->getBody()->getContents(), true);
        return $body['items'] ?? [];
    }

    public function getAccountProfiles(): array
    {
        $response = $this->api->request(self::ACCOUNT_PROFILES_URL);
        $body = json_decode($response->getBody()->getContents(), true);
        return $body['items'] ?? [];
    }

    public function getCustomMetrics(int $accountId, string $webPropertyId): array
    {
        $response = $this->api->request(sprintf(self::CUSTOM_METRICS_URL, $accountId, $webPropertyId));
        $body = json_decode($response->getBody()->getContents(), true);
        return $body['items'] ?? [];
    }

    public function request(string $method, string $url, ?array $body = null, ?array $query = null): array
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

    public function getBatch(array $query): array
    {
        return ($query['endpoint'] === 'mcf')
            ? $this->getMCFReports($query)
            : $this->getReports($query);
    }

    public function getPropertyReport(array $query, array $property): array
    {
        $url = sprintf(
            self::REPORTS_PROPERTIES_URL,
            $property['propertyKey']
        );

        $body = $this->getPropertyReportRequest($query['query']);

        $this->logger->debug(sprintf('Sending Report request'), [
            'request' => [
                'method' => 'POST',
                'url' => $url,
                'body' => $body,
            ],
        ]);

        return $this->processResponseProperty($this->request('POST', $url, $body), $query);
    }

    public function getReports(array $query): array
    {
        $body = [
            'reportRequests' => $this->getReportRequest($query['query']),
        ];
        $this->logger->debug(sprintf('Sending Report request'), [
            'ga_profile' => $query['query']['viewId'],
            'request' => [
                'method' => 'POST',
                'url' => self::REPORTS_URL,
                'body' => $body,
            ],
        ]);
        $reports = $this->request('POST', self::REPORTS_URL, $body);

        return $this->processResponse($reports, $query);
    }

    public function getMCFReports(array $query): array
    {
        $metrics = array_map(function ($item) {
            return $item['expression'];
        }, $query['query']['metrics']);

        $dimensions = array_map(function ($item) {
            return $item['name'];
        }, $query['query']['dimensions']);

        $params = [
            'ids' => sprintf('ga:%s', $query['query']['viewId']),
            'start-date' => date(
                'Y-m-d',
                (int) strtotime($this->getStartDate($query['query']['dateRanges'][0]['startDate']))
            ),
            'end-date' => date('Y-m-d', strtotime($query['query']['dateRanges'][0]['endDate'])),
            'metrics' => implode(',', $metrics),
            'dimensions' => implode(',', $dimensions),
            'samplingLevel' => $query['query']['samplingLevel'] ?? 'HIGHER_PRECISION',
            'start-index' => $query['query']['startIndex'] ?? 1,
            'max-results' => $query['query']['maxResults'] ?? 1000,
        ];

        if (!empty($query['query']['filtersExpression'])) {
            $params['filters'] = $query['query']['filtersExpression'];
        }

        $this->logger->debug(sprintf('Sending MCF request'), [
            'request' => [
                'ids' => $query['query']['viewId'],
                'method' => 'GET',
                'url' => self::MCF_URL,
                'params' => $params,
            ],
        ]);
        $reports = $this->request('GET', self::MCF_URL, null, $params);

        return $this->processResponseMCF($reports, $query);
    }

    private function getReportRequest(array $query): array
    {
        $query['dateRanges'] = array_map(function ($item) {
            return [
                'startDate' => date('Y-m-d', (int) strtotime($this->getStartDate($item['startDate']))),
                'endDate' => date('Y-m-d', strtotime($item['endDate'])),
            ];
        }, $query['dateRanges']);

        $dateRangesInfo = array_map(
            fn($v) => sprintf('%s - %s', $v['startDate'], $v['endDate']),
            $query['dateRanges']
        );
        $this->logger->info('Using Date Ranges: ' . implode(', ', $dateRangesInfo));

        $query['pageSize'] = 5000;
        $query['includeEmptyRows'] = true;
        $query['hideTotals'] = false;
        $query['hideValueRanges'] = true;
        $query['samplingLevel'] = empty($query['samplingLevel']) ? 'LARGE' : $query['samplingLevel'];

        return $query;
    }

    private function getPropertyReportRequest(array $query): array
    {
        return [
            'dateRanges' => array_map(function ($item) {
                return [
                    'startDate' => date('Y-m-d', (int) strtotime($this->getStartDate($item['startDate']))),
                    'endDate' => date('Y-m-d', strtotime($item['endDate'])),
                ];
            }, $query['dateRanges']),
            'keepEmptyRows' => true,
            'dimensions' => $query['dimensions'],
            'metrics' => $query['metrics'],
            'offset' => $query['offset'] ?? 0,
            'limit' => $query['maxResult'] ?? 5000,
        ];
    }

    private function processResponseProperty(array $response, array $query): array
    {
        $dataSet = [];
        $dimensionNames = array_map(fn($v) => $v['name'], $response['dimensionHeaders']);
        $metricNames = array_map(fn($v) => $v['name'], $response['metricHeaders']);

        $dimensions = [];
        $metrics = [];
        if (!empty($response['rows'])) {
            foreach ($response['rows'] as $row) {
                foreach ($row['dimensionValues'] as $k => $v) {
                    $dimensions[$dimensionNames[$k]] = $v['value'];
                }
                foreach ($row['metricValues'] as $k => $v) {
                    $metrics[$metricNames[$k]] = $v['value'];
                }
                $dataSet[] = new Result($metrics, $dimensions);
            }
        }

        return [
            'data' => $dataSet,
            'query' => $query,
            'totals' => $response['rowCount'] ?? 0,
            'rowCount' => isset($response['rows']) ? count($response['rows']) : 0,
        ];
    }

    private function processResponse(array $response, array $query): array
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
            'rowCount' => isset($report['data']['rowCount']) ? $report['data']['rowCount'] : 0,
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

    private function processResponseMCF(array $response, array $query): array
    {
        $rows = empty($response['rows']) ? [] : $response['rows'];

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
            'rowCount' => isset($response['totalResults']) ? $response['totalResults'] : 0,
        ];

        if (isset($response['sampleSize'])) {
            $processed['samplingSpaceSizes'] = $response['sampleSize'];
        }

        if (isset($response['sampleSpace'])) {
            $processed['samplesReadCounts'] = $response['sampleSpace'];
        }

        if (isset($response['nextLink'])) {
            $processed['nextLink'] = $response['nextLink'];
        }

        return $processed;
    }

    private function parseMCFValue(array $data, string $dataType): string
    {
        if ($dataType !== 'mcf_sequence') {
            return $data['primitiveValue'];
        }

        if (isset($data['conversionPathValue'])) {
            $path = [];
            foreach ($data['conversionPathValue'] as $node) {
                $path[] = $node['nodeValue'];
            }

            return implode(' > ', $path);
        }

        throw new ApplicationException(
            sprintf(
                'Error parsing MCF response rows: one of the keys "%s" is not supported',
                implode(',', array_keys($data))
            )
        );
    }

    public function getApiCallsCount(): int
    {
        return $this->apiCallsCount;
    }

    public static function getDelayFn(int $base = 5000): \Closure
    {
        return function ($retries) use ($base) {
            return (int) ($base * pow(2, $retries - 1) + rand(0, 500));
        };
    }

    public function getStartDate(string $startDate): string
    {
        if ($startDate === Config::STATE_LAST_RUN_DATE) {
            $startDate = $this->inputState[Config::STATE_LAST_RUN_DATE] ?? '2005-01-01';
        }
        return $startDate;
    }
}
