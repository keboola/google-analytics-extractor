<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor\Paginator;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Psr\Log\LoggerInterface;

class ProfilesPaginator implements IPaginator
{
    private Output $output;

    private Client $client;

    private LoggerInterface $logger;

    public function __construct(Output $output, Client $client, LoggerInterface $logger)
    {
        $this->output = $output;
        $this->client = $client;
        $this->logger = $logger;
    }

    public function getOutput(): Output
    {
        return $this->output;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function paginate(array $query, array $report, CsvFile $csvFile): void
    {
        $counter = 0;
        do {
            // writer first result
            $this->output->writeReport($csvFile, $report, $query['query']['viewId']);
            $counter += count($report['data']);

            $this->logger->info(sprintf('Downloaded %s/%s records.', $counter, $report['rowCount']));

            // get next page if there's any
            $nextQuery = null;
            if (isset($report['nextPageToken'])) {
                $nextQuery = $query;
                $nextQuery['query']['pageToken'] = $report['nextPageToken'];
                $report = $this->client->getBatch($nextQuery);
            }

            // paging for MCF
            if (isset($report['nextLink'])) {
                $nextQuery = $query;
                $nextQuery['query']['startIndex'] = $this->getStartIndex($report['nextLink']);
                $report = $this->client->getBatch($nextQuery);
            }
            $query = $nextQuery;
        } while ($query);
    }

    private function getStartIndex(string $link): string
    {
        $url = explode('?', $link);
        parse_str($url[1], $params);

        return $params['start-index'];
    }
}
