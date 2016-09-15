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

    public function run(array $queries, array $profiles)
    {
        $status = [];

        $paginator = new Paginator($this->output, $this->gaApi);

        foreach ($queries as $query) {

            $outputCsv = $this->output->createReport($query['outputTable']);

            foreach ($profiles as $profile) {
                if (empty($query['query']['viewId'])) {
                    $query['query']['viewId'] = (string) $profile['id'];
                } elseif ($query['query']['viewId'] != $profile['id']) {
                    continue;
                }

                $report = $this->getReport($query);
                if (empty($report['data'])) {
                    continue;
                }

                if (!empty($report['samplesReadCounts']) && !empty($report['samplingSpaceSizes'])) {
                    $this->logger->warning("Report contains sampled data");
                    if (!empty($query['antisampling'])) {
                        $this->logger->info(sprintf("Using antisampling algorithm '%s'", $query['antisampling']));
                        $antisampling = new Antisampling($paginator, $outputCsv);
                        $algorithm = $query['antisampling'];
                        $antisampling->$algorithm($query, $report);

                        $status[$query['name'][$profile['id']]] = 'ok';

                        continue;
                    }
                }

                $this->output->writeReport($outputCsv, $report, $profile['id']);

                $paginator->paginate($query, $report, $outputCsv);

                $status[$query['name']][$profile['id']] = 'ok';
            }
        }

        return $status;
    }

    private function getReport($query)
    {
        return $this->gaApi->getBatch($query);
    }

    public function getSampleReport($query)
    {
        $report = $this->getReport($query);

        $data = [];
        $rowCount = 0;
        if (!empty($report['data'])) {
            $report['data'] = array_slice($report['data'], 0, 20);

            $csvFile = $this->output->createReport(
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
