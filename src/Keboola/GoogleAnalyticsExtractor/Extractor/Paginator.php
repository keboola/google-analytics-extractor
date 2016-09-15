<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 15/09/16
 * Time: 14:16
 */

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;

class Paginator
{
    private $output;

    private $client;

    public function __construct(Output $output, Client $client)
    {
        $this->output = $output;
        $this->client = $client;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function paginate($query, $report)
    {
        $csvFile = $this->output->createReport($query['outputTable']);
        $this->output->writeReport($csvFile, $report, $query['query']['viewId']);

        do {
            $nextQuery = null;
            if (isset($report['nextPageToken'])) {
                $query['query']['pageToken'] = $report['nextPageToken'];
                $nextQuery = $query;
                $report = $this->client->getBatch($query);
                $this->output->appendReport($csvFile, $report, $query['query']['viewId']);
            }
            $query = $nextQuery;
        } while ($nextQuery);
    }
}
