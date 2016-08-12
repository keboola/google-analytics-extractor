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
use Symfony\Component\Yaml\Yaml;

class Output
{
    private $dataDir;

    private $outputBucket;

    public function __construct($dataDir, $outputBucket)
    {
        $this->dataDir = $dataDir;
        $this->outputBucket = $outputBucket;
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

    public function writeReport(CsvFile $csv, array $report, $profileId, $incremental = false)
    {
        $cnt = 0;
        /** @var Result $result */
        foreach ($report['data'] as $result) {
            $metrics = $this->formatResultKeys($result->getMetrics());
            $dimensions = $this->formatResultKeys($result->getDimensions());

            // CSV Header
            if ($cnt == 0 && !$incremental) {
                $headerRow = array_merge(
                    ['id', 'idProfile'],
                    array_keys($dimensions),
                    array_keys($metrics)
                );
                $csv->writeRow($headerRow);
            }

            if (isset($dimensions['date'])) {
                $dimensions['date'] = date('Y-m-d', strtotime($dimensions['date']));
            }

            $row = array_merge(array_values($dimensions), array_values($metrics));
            $outRow = array_merge(
                [sha1($profileId . implode('', $dimensions)), $profileId],
                $row
            );
            $csv->writeRow($outRow);
            $cnt++;
        }

        return $csv;
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

    public function createManifest($name, $primaryKey = null, $incremental = false)
    {
        $outFilename = $this->dataDir . '/out/tables/' . $name . '.csv.manifest';

        $manifestData = [
            'destination' => sprintf('%s.%s.csv', $this->outputBucket, $name),
            'incremental' => $incremental
        ];

        if ($primaryKey !== null) {
            $manifestData['primary_key'] = $primaryKey;
        }

        return file_put_contents($outFilename, Yaml::dump($manifestData));
    }
}
