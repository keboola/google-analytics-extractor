<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

class Usage
{
    private string $dataDir;

    private string $pathname;

    private int $apiCalls;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
        $this->pathname = $this->dataDir . '/out/usage.json';
    }

    public function getPathname(): string
    {
        return $this->pathname;
    }

    public function setApiCalls(int $value): void
    {
        $this->apiCalls = $value;
    }

    public function getApiCalls(): int
    {
        return $this->apiCalls;
    }

    public function write(): void
    {
        @unlink($this->pathname);
        if (!file_exists($this->dataDir . '/out')) {
            mkdir($this->dataDir . '/out', 0777, true);
        }
        touch($this->pathname);
        file_put_contents($this->pathname, json_encode([
            [
                'metric' => 'API Calls',
                'value' => $this->apiCalls,
            ],
        ]));
    }
}
