<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Result;

class Output
{
    private string $dataDir;

    private Usage $usage;

    private array $options;

    private string $outputBucket;

    public function __construct(string $dataDir, string $outputBucket, array $options = [])
    {
        $this->dataDir = $dataDir;
        $this->outputBucket = $outputBucket;
        $this->usage = new Usage($dataDir);
        $this->options = $options;
    }

    public function getUsage(): Usage
    {
        return $this->usage;
    }

    public function writeProfiles(CsvFile $csv, array $profiles): CsvFile
    {
        $csv->writeRow(['id', 'name', 'webPropertyId', 'webPropertyName', 'accountId', 'accountName']);
        foreach ($profiles as $profile) {
            $csv->writeRow([
                'id' => $profile['id'],
                'name' => $profile['name'],
                'webPropertyId' => $profile['webPropertyId'],
                'webPropertyName' => $profile['webPropertyName'],
                'accountId' => $profile['accountId'],
                'accountName' => $profile['accountName'],
            ]);
        }

        return $csv;
    }

    public function writeProperties(CsvFile $csv, array $properties): CsvFile
    {
        $csv->writeRow(['propertyKey', 'propertyName', 'accountKey', 'accountName']);
        foreach ($properties as $property) {
            $csv->writeRow([
                'propertyKey' => $property['propertyKey'],
                'propertyName' => $property['propertyName'],
                'accountKey' => $property['accountKey'],
                'accountName' => $property['accountName'],
            ]);
        }

        return $csv;
    }

    private function createHeaderRowFromQuery(array $query, string $type = 'idProfile'): array
    {
        $dimensions = array_map(function ($item) {
            return str_replace('ga:', '', $item['name']);
        }, $query['query']['dimensions']);

        $metrics = array_map(function ($item) {
            $metric = $item['expression'] ?? $item['name'];
            return str_replace('ga:', '', $metric);
        }, $query['query']['metrics']);

        return array_merge(
            ['id', $type],
            $dimensions,
            $metrics
        );
    }

    public function createReport(array $query, string $type = 'idProfile'): CsvFile
    {
        $csv = $this->createCsvFile($query['outputTable']);
        $csv->writeRow($this->createHeaderRowFromQuery($query, $type));
        return $csv;
    }

    public function createSampleReportJson(array $query, array $report): array
    {
        $columns = $this->createHeaderRowFromQuery($query);
        $rows = [];

        $profileId = $query['query']['viewId'];

        foreach ($report['data'] as $result) {
            $rows[] = array_combine($columns, $this->createReportRow($result, $profileId));
        }

        return $rows;
    }

    private function createReportRow(Result $reportDataItem, string $profileId): array
    {
        $metrics = $this->formatResultKeys($reportDataItem->getMetrics());
        $dimensions = $this->formatResultKeys($reportDataItem->getDimensions());

        if (isset($dimensions['date'])) {
            $dimensions['date'] = date('Y-m-d', strtotime($dimensions['date']));
        }

        $pKey = $this->getPrimaryKey($profileId, $dimensions);

        return array_merge(
            [$pKey, $profileId],
            array_values($dimensions),
            array_values($metrics)
        );
    }

    public function writeReport(CsvFile $csv, array $report, string $profileId): CsvFile
    {
        /** @var Result $result */
        foreach ($report['data'] as $result) {
            $csv->writeRow($this->createReportRow($result, $profileId));
        }

        return $csv;
    }

    private function getPrimaryKey(string $profileId, array $dimensions): string
    {
        // Backward compatibility with data with old (buggy) PKs
        if (isset($dimensions['date'])) {
            if ($this->isConflictingPrimaryKey() && strtotime($dimensions['date']) < strtotime('2018-03-27')) {
                return sha1($profileId . implode('', $dimensions));
            }
        }
        return sha1($profileId . implode('-', $dimensions));
    }

    private function isConflictingPrimaryKey(): bool
    {
        return !isset($this->options['nonConflictPrimaryKey']) || $this->options['nonConflictPrimaryKey'] === false;
    }

    private function formatResultKeys(array $metricsOrDimensions): array
    {
        $res = [];
        foreach ($metricsOrDimensions as $k => $v) {
            $res[str_replace('ga:', '', $k)] = $v;
        }
        return $res;
    }

    public function createCsvFile(string $name): CsvFile
    {
        $outTablesDir = $this->dataDir . '/out/tables';
        if (!is_dir($outTablesDir)) {
            mkdir($outTablesDir, 0777, true);
        }
        return new CsvFile($this->dataDir . '/out/tables/' . $name . '.csv');
    }

    public function createManifest(
        string $name,
        string $destination,
        ?array $primaryKey = null,
        bool $incremental = false
    ): void {
        $outFilename = $this->dataDir . '/out/tables/' . $name . '.manifest';

        $manifestData = [
            'destination' => sprintf('%s.%s', $this->outputBucket, $destination),
            'incremental' => $incremental,
        ];

        if ($primaryKey !== null) {
            $manifestData['primary_key'] = $primaryKey;
        }

        file_put_contents($outFilename, json_encode($manifestData));
    }
}
