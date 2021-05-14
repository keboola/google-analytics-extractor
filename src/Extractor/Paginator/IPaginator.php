<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor\Paginator;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;

interface IPaginator
{
    public function getOutput(): Output;
    public function getClient(): Client;
    public function paginate(array $query, array $report, CsvFile $csvFile): void;
}
