<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor\Paginator;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;

class PropertiesPaginator implements IPaginator
{
    private Output $output;

    private Client $client;

    private int $rowCounter = 0;

    private array $property;

    public function __construct(Output $output, Client $client)
    {
        $this->output = $output;
        $this->client = $client;
    }

    public function getOutput(): Output
    {
        return $this->output;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setProperty(array $property): self
    {
        $this->property = $property;
        return $this;
    }

    public function paginate(array $query, array $report, CsvFile $csvFile): void
    {
        do {
            $this->rowCounter += $report['rowCount'];

            // writer first result
            $propertyId = str_replace('properties/', '', $this->property['propertyKey']);
            $this->output->writeReport($csvFile, $report, $propertyId);

            // get next page if there's any
            $nextQuery = null;
            if ($report['totals'] > $this->rowCounter) {
                $nextQuery = $query;
                $nextQuery['query']['offset'] = $this->rowCounter;
                $report = $this->client->getPropertyReport($nextQuery, $this->property);
            }

            $query = $nextQuery;
        } while ($report['totals'] > $this->rowCounter);
    }
}
