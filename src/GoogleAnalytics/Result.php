<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

use Exception;

class Result
{
    private array $metrics = [];
    private array $dimensions = [];

    public function __construct(array $metrics, array $dimensions)
    {
        $this->metrics = $metrics;
        $this->dimensions = $dimensions;
    }

    public function __toString(): string
    {
        return implode(' ', $this->dimensions);
    }

    public function getDimensions(): array
    {
        return $this->dimensions;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function __call(string $name, array $parameters): string
    {
        if (!preg_match('/^get/', $name)) {
            throw new Exception('No such function "' . $name . '"');
        }
        $name = (string) preg_replace('/^get/', '', $name);

        $metricKey = self::arrayKeyExistsNc($name, $this->metrics);
        if ($metricKey) {
            return $this->metrics[$metricKey];
        }

        $dimensionKey = self::arrayKeyExistsNc($name, $this->dimensions);
        if ($dimensionKey) {
            return $this->dimensions[$dimensionKey];
        }

        throw new Exception('No valid metric or dimension called "' . $name . '"');
    }

    /**
     * @return mixed Matching array key or false
     */
    public static function arrayKeyExistsNc(string $key, array $search): mixed
    {
        if (array_key_exists($key, $search)) {
            return $key;
        }

        $key = strtolower($key);

        foreach ($search as $k => $v) {
            if (strtolower($k) === $key) {
                return $k;
            }
        }
        return false;
    }

    public function getDateFormatted(): string
    {
        $dateKey = $this->getDateKey($this->dimensions);
        $date = $this->dimensions[$dateKey];

        if ($date === '00000000') {
            $date = '19000101';
        }

        $hour = '00';
        $hourKey = self::arrayKeyExistsNc('ga:hour', $this->dimensions);
        if ($hourKey) {
            $hour = $this->dimensions[$hourKey];
        }
        $result = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) . ' ' . $hour . ':00:00';

        return $result;
    }

    private static function getDateKey(array $dimensions): mixed
    {
        return self::arrayKeyExistsNc('ga:date', $dimensions)
            || self::arrayKeyExistsNc('mcf:conversionDate', $dimensions);
    }
}
