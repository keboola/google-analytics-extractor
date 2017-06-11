<?php
/**
 * Author: miro@keboola.com
 * Date: 11/06/2017
 */

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

class UsageTest extends \PHPUnit_Framework_TestCase
{
    public function testSetApiCalls()
    {
        $usage = new Usage('/tmp/data');
        $usage->setApiCalls(123);
        $this->assertEquals(123, $usage->getApiCalls());
    }

    public function testWrite()
    {
        $usage = new Usage('/tmp/data');
        $usage->setApiCalls(123);
        $usage->write();

        $usageContent = file_get_contents($usage->getPathname());
        $this->assertEquals(
            json_encode([
                [
                    'metric' => 'API Calls',
                    'value' => 123
                ]
            ]),
            $usageContent
        );
    }
}
