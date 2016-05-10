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
            $csv->writeRow($profile);
        }

        return $csv;
	}

	public function writeReport(CsvFile $csv, array $report, $profileId, $incremental = false)
	{
        $cnt = 0;
        /** @var Result $result */
        foreach ($report['data'] as $result) {
            $metrics = $result->getMetrics();
            $dimensions = $result->getDimensions();

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

        return file_put_contents($outFilename , Yaml::dump($manifestData));
    }
}
