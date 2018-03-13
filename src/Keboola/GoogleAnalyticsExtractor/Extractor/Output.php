<?php
/**
 * DataManager.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 29.7.13
 */

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Result;

class Output
{
    private $dataDir;

    private $outputBucket;

    /** @var Usage */
    private $usage;

    private $version;

    public function __construct($dataDir, $outputBucket, $version = '4')
    {
        $this->dataDir = $dataDir;
        $this->outputBucket = $outputBucket;
        $this->usage = new Usage($dataDir);
        $this->version = $version;
    }

    public function getUsage()
    {
        return $this->usage;
    }

    public function writeProfiles(CsvFile $csv, array $profiles)
    {
        $csv->writeRow(['id', 'name', 'webPropertyId', 'webPropertyName', 'accountId', 'accountName']);
        foreach ($profiles as $profile) {
            $csv->writeRow([
                'id' => $profile['id'],
                'name' => $profile['name'],
                'webPropertyId' => $profile['webPropertyId'],
                'webPropertyName' => $profile['webPropertyName'],
                'accountId' => $profile['accountId'],
                'accountName' => $profile['accountName']
            ]);
        }

        return $csv;
    }

    public function createReport($query)
    {
        $csv = $this->createCsvFile(sprintf('%s_%s', $query['outputTable'], uniqid()));

        $dimensions = array_map(function ($item) {
            return str_replace('ga:', '', $item['name']);
        }, $query['query']['dimensions']);

        $metrics = array_map(function ($item) {
            return str_replace('ga:', '', $item['expression']);
        }, $query['query']['metrics']);

        $headerRow = array_merge(
            ['id', 'idProfile'],
            $dimensions,
            $metrics
        );
        $csv->writeRow($headerRow);

        return $csv;
    }

    /**
     * Write report data to output csv file (with header)
     *
     * @param CsvFile $csv
     * @param array $report
     * @param $profileId
     * @return CsvFile
     * @throws \Keboola\Csv\Exception
     */
    public function writeReport(CsvFile $csv, array $report, $profileId)
    {
        /** @var Result $result */
        foreach ($report['data'] as $result) {
            $metrics = $this->formatResultKeys($result->getMetrics());
            $dimensions = $this->formatResultKeys($result->getDimensions());

            if (isset($dimensions['date'])) {
                $dimensions['date'] = date('Y-m-d', strtotime($dimensions['date']));
            }

            $pKey = $this->getPrimaryKey($profileId, $dimensions);

            $csv->writeRow(array_merge(
                [$pKey, $profileId],
                array_values($dimensions),
                array_values($metrics)
            ));
        }

        return $csv;
    }

    private function getPrimaryKey($profileId, $dimensions)
    {
        // Backward compatibility with data with old (buggy) PKs
        if (isset($dimensions['date'])) {
            if ($this->version < 5 && strtotime($dimensions['date']) < strtotime('2018-03-20')) {
                return sha1($profileId . implode('', $dimensions));
            }
        }
        return sha1($profileId . implode('-', $dimensions));
    }

    private function formatResultKeys($metricsOrDimensions)
    {
        $res = [];
        foreach ($metricsOrDimensions as $k => $v) {
            $res[str_replace('ga:', '', $k)] = $v;
        }
        return $res;
    }

    public function createCsvFile($name)
    {
        $outTablesDir = $this->dataDir . '/out/tables';
        if (!is_dir($outTablesDir)) {
            mkdir($outTablesDir, 0777, true);
        }
        return new CsvFile($this->dataDir . '/out/tables/' . $name . '.csv');
    }

    public function createManifest($name, $destination, $primaryKey = null, $incremental = false)
    {
        $outFilename = $this->dataDir . '/out/tables/' . $name . '.manifest';

        $manifestData = [
            'destination' => sprintf('%s.%s', $this->outputBucket, $destination),
            'incremental' => $incremental
        ];

        if ($primaryKey !== null) {
            $manifestData['primary_key'] = $primaryKey;
        }

        return file_put_contents($outFilename, json_encode($manifestData));
    }
}
