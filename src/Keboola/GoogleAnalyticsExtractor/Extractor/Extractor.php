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
	private $config;

	/** @var Client */
	private $gaApi;

	/** @var Output */
	private $output;

	/** @var Logger */
	private $logger;

	private $currAccountId;

	public function __construct($config, Client $gaApi, Logger $logger)
	{
		$this->config = $config;
		$this->gaApi = $gaApi;
		$this->logger = $logger;
		$this->output = new Output($config['data_dir']);

		$this->gaApi->getApi()->setBackoffsCount(7);
		$this->gaApi->getApi()->setBackoffCallback403($this->getBackoffCallback403());
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

	public function run(array $queries, array $profile)
	{
		$status = [];

		$this->gaApi->getApi()->setCredentials(
			$this->config['authorization']['oauth_api']['access_token'],
			$this->config['authorization']['oauth_api']['refresh_token']
		);
		$this->gaApi->getApi()->setRefreshTokenCallback([$this, 'refreshTokenCallback']);

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

	protected function extract($queries, $profileId)
	{
		$reports = $this->gaApi->getBatch($queries);

		$this->logger->info("Extracting ...", [
			'queries' => $queries,
			'results' => count($reports)
		]);

        $hasNextPages = false;
        do {
            $nextQueries = [];
            foreach ($reports as $reportKey => $report) {
                $this->output->writeReport($report, $profileId, true);

                // pagination
                if (isset($report['nextPageToken'])) {
                    $queries[$reportKey]['pageToken'] = $report['nextPageToken'];
                    $nextQueries[] = $queries[$reportKey];
                }
                $hasNextPages = !empty($nextQueries);
            }

            if ($hasNextPages) {
                $reports = $this->gaApi->getBatch($nextQueries);
            }
        } while ($hasNextPages);
	}

	public function setCurrAccountId($id)
	{
		$this->currAccountId = $id;
	}

	public function getCurrAccountId()
	{
		return $this->currAccountId;
	}

	public function refreshTokenCallback($accessToken, $refreshToken)
	{
//		$account = $this->configuration->getAccountBy('accountId', $this->currAccountId);
//		$account->setAccessToken($accessToken);
//		$account->setRefreshToken($refreshToken);
//		$account->save();
	}
}

