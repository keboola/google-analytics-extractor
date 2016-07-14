<?php
/**
 * Extractor.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 29.7.13
 */

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use GuzzleHttp\Exception\RequestException;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use Keboola\GoogleAnalyticsExtractor\Exception\UserException;
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

	public function run(array $queries, array $profile)
	{
		$status = [];

		try {
			$this->extract($queries, $profile['id']);
            $status[$profile['name']] = 'ok';
		} catch (RequestException $e) {
			if ($e->getCode() == 401) {
				throw new UserException("Expired or wrong credentials, please reauthorize.", $e);
			}
			if ($e->getCode() == 403) {
				if (strtolower($e->getResponse()->getReasonPhrase()) == 'forbidden') {
					$this->logger->warning("You don't have access to Google Analytics resource. Probably you don't have access to profile, or profile doesn't exists anymore.");
                    return $status;
				} else {
					throw new UserException("Reason: " . $e->getResponse()->getReasonPhrase(), $e);
				}
			}
			if ($e->getCode() == 400) {
				throw new UserException($e->getMessage());
			}
			if ($e->getCode() == 503) {
				throw new UserException("Google API error: " . $e->getMessage(), $e);
			}
			throw new ApplicationException($e->getResponse()->getBody(), 500, $e);
		}

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

            $report = $this->getReport($query);
            if ($report == null) {
                continue;
            }
            $this->logger->debug("Extracting ...", [
                'query' => $query
            ]);

            $createOutputFile = true;
            $csvFile = null;
            do {
                $nextQuery = null;
                if ($createOutputFile) {
                    $csvFile = $this->createOutputFile(
                        $query['outputTable']
                    );
                }
                $this->output->writeReport($csvFile, $report, $profileId);

                // pagination
                if (isset($report['nextPageToken'])) {
                    $query['query']['pageToken'] = $report['nextPageToken'];
                    $nextQuery = $query;
                }

                $hasNextPages = !is_null($nextQuery);
                $createOutputFile = false;

                if ($hasNextPages) {
                    $report = $this->getReport($nextQuery);
                }
                $query = $nextQuery;
            } while ($hasNextPages);
        }
	}

    private function getReport($query)
    {
        return $this->gaApi->getBatch($query);
    }

    private function createOutputFile($filename, $primaryKey = ['id'], $incremental = true)
    {
        $this->output->createManifest($filename, $primaryKey, $incremental);
        return $this->output->createCsvFile($filename);
    }

	public function refreshTokenCallback($accessToken, $refreshToken)
	{
	}

    public function getBackoffCallback403()
    {
        return function ($response) {
            /** @var ResponseInterface $response */
            $reason = $response->getReasonPhrase();

            if (
                $reason == 'insufficientPermissions'
                || $reason == 'dailyLimitExceeded'
                || $reason == 'usageLimits.userRateLimitExceededUnreg'
            ) {
                return false;
            }

            return true;
        };
    }
}

