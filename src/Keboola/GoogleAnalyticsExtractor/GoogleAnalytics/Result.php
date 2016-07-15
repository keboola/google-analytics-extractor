<?php
/**
 * Result.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.7.13
 */

namespace Keboola\GoogleAnalyticsExtractor\GoogleAnalytics;

class Result
{
    private $metrics = array();
    private $dimensions = array();

    public function __construct($metrics, $dimensions)
    {
        $this->metrics = $metrics;
        $this->dimensions = $dimensions;
    }

    /**
     * toString function to return the name of the result
     * this is a concatented string of the dimesions chosen
     *
     * @return String
     */
    public function __toString()
    {
        if (is_array($this->dimensions)) {
            return implode(' ', $this->dimensions);
        } else {
            return '';
        }
    }

    /**
     * Get an associative array of the dimesions
     * and the matching values for the current result
     *
     * @return Array
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * Get an array of the metrics and the matchning
     * values for the current result
     *
     * @return Array
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * Call method to find a matching metric or dimension to return
     *
     * @param $name String name of function called
     * @param $parameters
     * @throws \Exception
     * @return String
     */
    public function __call($name, $parameters)
    {
        if (!preg_match('/^get/', $name)) {
            throw new \Exception('No such function "' . $name . '"');
        }
        $name = preg_replace('/^get/', '', $name);

        $metricKey = self::arrayKeyExistsNc($name, $this->metrics);
        if ($metricKey) {
            return $this->metrics[$metricKey];
        }

        $dimensionKey = self::arrayKeyExistsNc($name, $this->dimensions);
        if ($dimensionKey) {
            return $this->dimensions[$dimensionKey];
        }

        throw new \Exception('No valid metric or dimesion called "' . $name . '"');
    }

    /**
     * Case insensitive array_key_exists function, also returns
     * matching key.
     *
     * @param String $key
     * @param Array $search
     * @return String Matching array key
     */
    public static function arrayKeyExistsNc($key, $search)
    {
        if (array_key_exists($key, $search)) {
            return $key;
        }

        if (!(is_string($key) && is_array($search))) {
            return false;
        }
        $key = strtolower($key);

        foreach ($search as $k => $v) {
            if (strtolower($k) == $key) {
                return $k;
            }
        }
        return false;
    }

    /**
     * Returns date (or date time if hour dimension is in the dimension array)
     * formatted to GoodDate friendly format
     *
     * @return string
     */
    public function getDateFormatted()
    {
        $dateKey = self::arrayKeyExistsNc('date', $this->dimensions);
        $date = $this->dimensions[$dateKey];

        if ($date == '00000000') {
            $date = '19000101';
        }

        $hour = '00';
        if ($hourKey = self::arrayKeyExistsNc('hour', $this->dimensions)) {
            $hour = $this->dimensions[$hourKey];
        }
        $result = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) . ' ' . $hour . ':00:00';

        return $result;
    }
}
