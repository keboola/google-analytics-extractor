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

    public function getOutput()
    {
        return $this->output;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function paginate($query, $report, $csvFile = null)
    {
        do {
            // writer first result
            $this->output->writeReport($csvFile, $report, $query['query']['viewId']);

            // get next page if there's any
            $nextQuery = null;
            if (isset($report['nextPageToken'])) {
                $nextQuery = $query;
                $nextQuery['query']['pageToken'] = $report['nextPageToken'];
                $report = $this->client->getBatch($nextQuery);
            }
            $query = $nextQuery;
        } while ($nextQuery);
    }
}
