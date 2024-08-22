<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use PHPUnit\Framework\TestCase;

class UsageTest extends TestCase
{
    public function testSetApiCalls(): void
    {
        $usage = new Usage('/tmp/data');
        $usage->setApiCalls(123);
        $this->assertEquals(123, $usage->getApiCalls());
    }

    public function testWrite(): void
    {
        $usage = new Usage('/tmp/data');
        $usage->setApiCalls(123);
        $usage->write();

        $usageContent = file_get_contents($usage->getPathname());
        $this->assertEquals(
            json_encode([
                [
                    'metric' => 'API Calls',
                    'value' => 123,
                ],
            ]),
            $usageContent,
        );
    }
}
