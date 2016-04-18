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

	public function __construct($dataDir)
	{
		$this->dataDir = $dataDir;
	}

	public function writeProfiles(array $profiles)
	{
        $csv = $this->createOutputCsv('profiles');

        foreach ($profiles as $profile) {
            $csv->writeRow($profile);
        }

        return $csv;
	}

	public function writeReport(array $report, $profileId, $incremental = false)
	{
        $cnt = 0;
        $csv = $this->createOutputCsv($report['queryName']);

        /** @var Result $result */
        foreach ($report as $result) {
            $metrics = $result->getMetrics();
            $dimensions = $result->getDimensions();

            // CSV Header
            if ($cnt == 0 && !$incremental) {
                $headerRow = array_merge(
                    array('id', 'idProfile'),
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
                array(sha1($profileId . implode('', $dimensions)), $profileId),
                $row
            );
            $csv->writeRow($outRow);

            $cnt++;
        }

        return $csv;
	}

	protected function createOutputCsv($name)
	{
		$outTablesDir = $this->dataDir . '/out/tables';
		if (!is_dir($outTablesDir)) {
			mkdir($outTablesDir, 0777, true);
		}
		return new CsvFile($this->dataDir . '/out/tables/' . $name . '.csv');
	}
}
