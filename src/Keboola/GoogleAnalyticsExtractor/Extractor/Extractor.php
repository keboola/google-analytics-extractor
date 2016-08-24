<?php
/**
 * Extractor.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 29.7.13
 */

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;

class Extractor
{
    /** @var Client */
    private $gaApi;

    /** @var Output */
    private $output;

    /** @var Logger */
    private $logger;

    public function __construct(Client $gaApi, Output $output, Logger $logger)
    {
        $this->gaApi = $gaApi;
        $this->logger = $logger;
        $this->output = $output;

        $this->gaApi->getApi()->setBackoffsCount(7);
        $this->gaApi->getApi()->setBackoffCallback403($this->getBackoffCallback403());
        $this->gaApi->getApi()->setRefreshTokenCallback([$this, 'refreshTokenCallback']);
    }

    public function getBackoffCallback403()
    {
        return function ($response) {
            /** @var ResponseInterface $response */
            $reason = $response->getReasonPhrase();

            if ($reason == 'insufficientPermissions'
                || $reason == 'dailyLimitExceeded'
                || $reason == 'usageLimits.userRateLimitExceededUnreg'
            ) {
                return false;
            }

            return true;
        };
    }

    public function run(array $queries, array $profile)
    {
        $status = [];
        $this->extract($queries, $profile['id']);
        $status[$profile['name']] = 'ok';

        return $status;
    }

    private function extract($queries, $profileId)
    {
        foreach ($queries as $query) {
            if (empty($query['query']['viewId'])) {
                $query['query']['viewId'] = (string) $profileId;
            } elseif ($query['query']['viewId'] != $profileId) {
                continue;
            }

            $this->logger->debug("Extracting ...", [
                'query' => $query
            ]);

            $report = $this->getReport($query);
            if (empty($report['data'])) {
                continue;
            }

            $csvFile = $this->createOutputFile(
                $query['outputTable']
            );

            $this->output->writeReport($csvFile, $report, $profileId);

            // pagination
            do {
                $nextQuery = null;
                if (isset($report['nextPageToken'])) {
                    $query['query']['pageToken'] = $report['nextPageToken'];
                    $nextQuery = $query;
                    $report = $this->getReport($nextQuery);
                    $this->output->appendReport($csvFile, $report, $profileId);
                }
                $query = $nextQuery;
            } while ($nextQuery);
        }
    }

    private function getReport($query)
    {
        return $this->gaApi->getBatch($query);
    }

    private function createOutputFile($destination, $primaryKey = ['id'], $incremental = true)
    {
        $filename = sprintf('%s_%s', $destination, uniqid());
        $this->output->createManifest($filename, $destination, $primaryKey, $incremental);
        return $this->output->createCsvFile($filename);
    }

    public function getSampleReport($query)
    {
        $report = $this->getReport($query);

        $data = [];
        $rowCount = 0;
        if (!empty($report['data'])) {
            $report['data'] = array_slice($report['data'], 0, 20);

            $csvFile = $this->createOutputFile(
                $query['outputTable']
            );
            $this->output->writeReport($csvFile, $report, $query['query']['viewId']);

            $data = file_get_contents($csvFile);
            $rowCount = $report['rowCount'];
        }

        return [
            'status' => 'success',
            'viewId' => $query['query']['viewId'],
            'data' => $data,
            'rowCount' => $rowCount
        ];
    }

    public function getSegments()
    {
        $segments = $this->gaApi->getSegments();

        return [
            'status' => 'success',
            'data' => $segments,
        ];
    }

    public function getCustomMetrics($accountId, $webPropertyId)
    {
        $metrics = $this->gaApi->getCustomMetrics($accountId, $webPropertyId);

        return [
            'status' => 'success',
            'data' => $metrics,
        ];
    }

    public function refreshTokenCallback($accessToken, $refreshToken)
    {
    }
}
