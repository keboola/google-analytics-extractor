<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor\Antisampling;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use Keboola\GoogleAnalyticsExtractor\Extractor\Paginator\IPaginator;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Result;

class AntisamplingProperty implements IAntisampling
{
    private IPaginator $paginator;

    private Client $client;

    private CsvFile $outputCsv;

    private array $property;

    public function __construct(IPaginator $paginator, CsvFile $outputCsv, array $property)
    {
        $this->paginator = $paginator;
        $this->client = $paginator->getClient();
        $this->outputCsv = $outputCsv;
        $this->property = $property;
    }

    public function dailyWalk(array $query): void
    {
        unset($query['query']['pageToken']);
        $dateRanges = $query['query']['dateRanges'][0];
        $startDate = new \DateTime($this->client->getStartDate($dateRanges['startDate']));
        $endDate = new \DateTime($dateRanges['endDate']);

        while ($startDate->diff($endDate)->format('%r%a') >= 0) {
            $startDateString = $startDate->format('Y-m-d');

            $query['query']['dateRanges'][0] = [
                'startDate' => $startDateString,
                'endDate' => $startDateString,
            ];

            $report = $this->client->getPropertyReport($query, $this->property);

            $this->writeReport($query, $report);

            $startDate->modify('+1 Day');
        }
    }

    public function adaptive(array $query, array $report): void
    {
        throw new ApplicationException('Adaptive antisampling method is not implemented.');
    }

    private function writeReport(array $query, array $report): void
    {
        $this->paginator->paginate($query, $report, $this->outputCsv);
    }
}
