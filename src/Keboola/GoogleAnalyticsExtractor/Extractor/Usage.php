<?php
/**
 * Author: miro@keboola.com
 * Date: 11/06/2017
 */

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

class Usage
{
    private $dataDir;

    private $pathname;

    private $apiCalls;

    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
        $this->pathname = $this->dataDir . '/out/usage.json';
    }

    public function getPathname()
    {
        return $this->pathname;
    }

    public function setApiCalls($value)
    {
        $this->apiCalls = $value;
    }

    public function getApiCalls()
    {
        return $this->apiCalls;
    }

    public function write()
    {
        @unlink($this->pathname);
        if (!file_exists($this->dataDir . '/out')) {
            mkdir($this->dataDir . '/out', 0777, true);
        }
        touch($this->pathname);
        file_put_contents($this->pathname, json_encode([
            [
                'metric' => 'API Calls',
                'value' => $this->apiCalls
            ]
        ]));
    }
}
