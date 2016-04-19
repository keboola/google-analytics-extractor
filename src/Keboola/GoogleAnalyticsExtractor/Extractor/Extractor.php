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

	/*protected function getData($profile, $tableName, $dateFrom, $dateTo, $antisampling = false)
	{
		// Optimize sampling if configured
		if ($antisampling) {
			$dt = new \DateTime($dateFrom);
			$dtTo = new \DateTime($dateTo);
			$dtTo->modify("+1 day");

			while ($dt->format('Y-m-d') != $dtTo->format('Y-m-d')) {
				$this->extract($account, $profile, $tableName, $dt->format('Y-m-d'), $dt->format('Y-m-d'));
				$dt->modify("+1 day");
			}
		} else {
			$this->extract($account, $profile, $tableName, $dateFrom, $dateTo);
		}
	}*/

	private function extract($queries, $profileId)
	{
        $queries = $this->addViewIdToQueries($queries, $profileId);
		$reports = $this->gaApi->getBatch($queries);

		$this->logger->debug("Extracting ...", [
			'queries' => $queries,
			'results' => count($reports)
		]);

        $hasNextPages = false;
        $createOutputFile = true;
        $csvFiles = [];
        do {
            $nextQueries = [];
            foreach ($reports['reports'] as $reportKey => $report) {
                if ($createOutputFile) {
                    $csvFiles[$report['queryName']] = $this->output->createCsvFile($report['queryName']);
                }
                $this->output->writeReport($csvFiles[$report['queryName']], $report, $profileId);

                // pagination
                if (isset($report['nextPageToken'])) {
                    $queries[$reportKey]['query']['pageToken'] = $report['nextPageToken'];
                    $nextQueries[] = $queries[$reportKey];
                }

                $hasNextPages = !empty($nextQueries);
            }
            $createOutputFile = false;

            if ($hasNextPages) {
                $nextQueries = $this->addViewIdToQueries($nextQueries, $profileId);
                $reports = $this->gaApi->getBatch($nextQueries);
            }
        } while ($hasNextPages);
	}

    private function addViewIdToQueries($queries, $profileId)
    {
        foreach ($queries as $k => &$query) {
            if (empty($query['query']['viewId'])) {
                $query['query']['viewId'] = (string) $profileId;
            } elseif ($query['query']['viewId'] != $profileId) {
                unset($queries[$k]);
            }
        }

        return $queries;
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

