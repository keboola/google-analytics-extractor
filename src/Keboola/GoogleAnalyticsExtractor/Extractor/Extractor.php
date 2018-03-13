<?php
/**
 * Extractor.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 29.7.13
 */

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use GuzzleHttp\Exception\RequestException;
use Keboola\GoogleAnalyticsExtractor\Exception\UserException;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Keboola\GoogleAnalyticsExtractor\Logger\Logger;
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

        $this->gaApi->getApi()->setBackoffCallback403($this->getBackoffCallback403());
        $this->gaApi->getApi()->setRefreshTokenCallback([$this, 'refreshTokenCallback']);
    }

    public function getBackoffCallback403()
    {
        $falseReasons = [
            'insufficientPermissions',
            'dailyLimitExceeded',
            'usageLimits.userRateLimitExceededUnreg'
        ];

        return function ($response) use ($falseReasons) {
            /** @var ResponseInterface $response */
            $reason = $response->getReasonPhrase();

            return !in_array($reason, $falseReasons);
        };
    }

    public function run(array $queries, array $profiles)
    {
        $status = [];
        $paginator = new Paginator($this->output, $this->gaApi);

        foreach ($queries as $query) {
            $outputCsv = $this->output->createReport($query);
            $this->output->createManifest($outputCsv->getFilename(), $query['outputTable'], ['id'], true);
            $this->logger->info(sprintf("Running query '%s'", $query['name']));

            foreach ($profiles as $profile) {
                $apiQuery = $query;
                if (empty($query['query']['viewId'])) {
                    $apiQuery['query']['viewId'] = (string) $profile['id'];
                } elseif ($query['query']['viewId'] != $profile['id']) {
                    continue;
                }

                try {
                    $report = $this->getReport($apiQuery);
                } catch (RequestException $e) {
                    if ($e->getCode() == 403) {
                        if (strtolower($e->getResponse()->getReasonPhrase()) == 'forbidden') {
                            $this->logger->warning(sprintf(
                                "You don't have access to Google Analytics resource. 
                                Probably you don't have access to profile (%s), or it doesn't exists anymore.",
                                $profile['id']
                            ));
                            continue;
                        }
                    }
                    throw $e;
                }

                if (empty($report['data'])) {
                    continue;
                }

                if (!empty($query['antisampling'])) {
                    if (!$this->hasDimension($query, 'ga:date')
                        && !$this->hasDimension($query, 'ga:dateHour')
                        && !$this->hasDimension($query, 'ga:dateHourMinute')
                    ) {
                        throw new UserException(sprintf(
                            'At least one of these dimensions must be set in order to use anti-sampling: %s',
                            implode(' | ', ['ga:date', 'ga:dateHour', 'ga:dateHourMinute'])
                        ));
                    }

                    $isSampled = !empty($report['samplesReadCounts']) && !empty($report['samplingSpaceSizes']);

                    if ($isSampled) {
                        $this->logger->warning(sprintf(
                            "Report contains sampled data. Sampling rate is %d%%.",
                            intval(100 * (
                                intval($report['samplesReadCounts'][0])
                                / intval($report['samplingSpaceSizes'][0])
                            ))
                        ));
                    }

                    if ($isSampled || $query['antisampling'] == 'dailyWalk') {
                        $this->logger->info(sprintf("Using antisampling algorithm '%s'", $query['antisampling']));
                        $antisampling = new Antisampling($paginator, $outputCsv);
                        $algorithm = $query['antisampling'];
                        $antisampling->$algorithm($apiQuery, $report);

                        $status[$query['name']][$profile['id']] = 'ok';
                        continue;
                    }
                }

                $paginator->paginate($apiQuery, $report, $outputCsv);

                $status[$query['name']][$profile['id']] = 'ok';
            }
        }

        $usage = $this->output->getUsage();
        $usage->setApiCalls($this->gaApi->getApiCallsCount());
        $usage->write();

        return [
            'status' => 'success',
            'queries' => $status
        ];
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

            $csvFile = $this->output->createReport($query);
            $this->output->writeReport($csvFile, $report, $query['query']['viewId']);

            $data = file_get_contents($csvFile);
            $rowCount = $report['rowCount'];

            // remove created output files, so they won't be uploaded to Storage
            unlink($csvFile->getPathname());
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

    private function hasDimension($query, $name)
    {
        foreach ($query['query']['dimensions'] as $dimension) {
            if ($dimension['name'] == $name) {
                return true;
            }
        }
        return false;
    }
}
