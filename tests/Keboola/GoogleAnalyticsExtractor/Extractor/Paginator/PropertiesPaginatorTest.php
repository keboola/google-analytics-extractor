<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor\Paginator;

use Keboola\Csv\CsvFile;
use Keboola\GoogleAnalyticsExtractor\Extractor\Output;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PropertiesPaginatorTest extends TestCase
{
    private PropertiesPaginator $paginator;
    private MockObject $output;
    private MockObject $client;
    private MockObject $logger;

    public function setUp(): void
    {
        $this->output = $this->createMock(Output::class);
        $this->client = $this->createMock(Client::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->paginator = new PropertiesPaginator(
            $this->output,
            $this->client,
            $this->logger,
        );
    }

    public function testPaginateSinglePage(): void
    {
        $property = [
            'propertyKey' => 'properties/123456789',
            'propertyName' => 'Test Property',
        ];

        $query = [
            'query' => [
                'dimensions' => [['name' => 'ga:date']],
                'metrics' => [['name' => 'ga:sessions']],
                'dateRanges' => [
                    ['startDate' => '2023-01-01', 'endDate' => '2023-01-31'],
                ],
            ],
        ];

        $report = [
            'data' => [
                new Result(['ga:sessions' => '100'], ['ga:date' => '2023-01-01']),
                new Result(['ga:sessions' => '150'], ['ga:date' => '2023-01-02']),
            ],
            'totals' => 2,
            'rowCount' => 2,
        ];

        /** @var CsvFile $csvFile */
        $csvFile = $this->createMock(CsvFile::class);

        $this->paginator->setProperty($property);

        // Expect writeReport to be called once with the correct property ID
        $this->output->expects($this->once())
            ->method('writeReport')
            ->with($csvFile, $report, '123456789');

        // Expect logger to be called with progress info
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Downloaded 2/2 records.');

        $result = $this->paginator->paginate($query, $report, $csvFile);

        $this->assertEquals(2, $result);
    }

    public function testPaginateMultiplePages(): void
    {
        $property = [
            'propertyKey' => 'properties/123456789',
            'propertyName' => 'Test Property',
        ];

        $query = [
            'query' => [
                'dimensions' => [['name' => 'ga:date']],
                'metrics' => [['name' => 'ga:sessions']],
                'dateRanges' => [
                    ['startDate' => '2023-01-01', 'endDate' => '2023-01-31'],
                ],
            ],
        ];

        $firstReport = [
            'data' => [
                new Result(['ga:sessions' => '100'], ['ga:date' => '2023-01-01']),
                new Result(['ga:sessions' => '150'], ['ga:date' => '2023-01-02']),
            ],
            'totals' => 4,
            'rowCount' => 2,
        ];

        $secondReport = [
            'data' => [
                new Result(['ga:sessions' => '200'], ['ga:date' => '2023-01-03']),
                new Result(['ga:sessions' => '250'], ['ga:date' => '2023-01-04']),
            ],
            'totals' => 4,
            'rowCount' => 2,
        ];

        /** @var CsvFile $csvFile */
        $csvFile = $this->createMock(CsvFile::class);

        $this->paginator->setProperty($property);

        // Expect writeReport to be called twice
        $this->output->expects($this->exactly(2))
            ->method('writeReport')
            ->with($csvFile, $this->isType('array'), '123456789');

        // Expect logger to be called twice with progress info
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnOnConsecutiveCalls(
                $this->returnValue(null),
                $this->returnValue(null),
            );

        // Expect client to be called once for the second page
        $this->client->expects($this->once())
            ->method('getPropertyReport')
            ->with(
                [
                    'query' => [
                        'dimensions' => [['name' => 'ga:date']],
                        'metrics' => [['name' => 'ga:sessions']],
                        'dateRanges' => [
                            ['startDate' => '2023-01-01', 'endDate' => '2023-01-31'],
                        ],
                        'offset' => 2,
                    ],
                ],
                $property,
            )
            ->willReturn($secondReport);

        $result = $this->paginator->paginate($query, $firstReport, $csvFile);

        $this->assertEquals(4, $result);
    }

    public function testPaginateEmptyData(): void
    {
        $property = [
            'propertyKey' => 'properties/123456789',
            'propertyName' => 'Test Property',
        ];

        $query = [
            'query' => [
                'dimensions' => [['name' => 'ga:date']],
                'metrics' => [['name' => 'ga:sessions']],
                'dateRanges' => [
                    ['startDate' => '2023-01-01', 'endDate' => '2023-01-31'],
                ],
            ],
        ];

        $report = [
            'data' => [],
            'totals' => 0,
            'rowCount' => 0,
        ];

        /** @var CsvFile $csvFile */
        $csvFile = $this->createMock(CsvFile::class);

        $this->paginator->setProperty($property);

        // Expect writeReport to be called once even with empty data
        $this->output->expects($this->once())
            ->method('writeReport')
            ->with($csvFile, $report, '123456789');

        // Expect logger to be called with progress info
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Downloaded 0/0 records.');

        $result = $this->paginator->paginate($query, $report, $csvFile);

        $this->assertEquals(0, $result);
    }

    public function testPaginateWithOffsetInQuery(): void
    {
        $property = [
            'propertyKey' => 'properties/987654321',
            'propertyName' => 'Test Property 2',
        ];

        $query = [
            'query' => [
                'dimensions' => [['name' => 'ga:date']],
                'metrics' => [['name' => 'ga:sessions']],
                'dateRanges' => [
                    ['startDate' => '2023-01-01', 'endDate' => '2023-01-31'],
                ],
                'offset' => 10, // Existing offset should be preserved
            ],
        ];

        $firstReport = [
            'data' => [
                new Result(['ga:sessions' => '100'], ['ga:date' => '2023-01-01']),
            ],
            'totals' => 3,
            'rowCount' => 1,
        ];

        $secondReport = [
            'data' => [
                new Result(['ga:sessions' => '200'], ['ga:date' => '2023-01-02']),
            ],
            'totals' => 3,
            'rowCount' => 1,
        ];

        $thirdReport = [
            'data' => [
                new Result(['ga:sessions' => '300'], ['ga:date' => '2023-01-03']),
            ],
            'totals' => 3,
            'rowCount' => 1,
        ];

        /** @var CsvFile $csvFile */
        $csvFile = $this->createMock(CsvFile::class);

        $this->paginator->setProperty($property);

        // Expect writeReport to be called three times
        $this->output->expects($this->exactly(3))
            ->method('writeReport')
            ->with($csvFile, $this->isType('array'), '987654321');

        // Expect logger to be called three times
        $this->logger->expects($this->exactly(3))
            ->method('info')
            ->willReturnOnConsecutiveCalls(
                $this->returnValue(null),
                $this->returnValue(null),
                $this->returnValue(null),
            );

        // Expect client to be called twice for subsequent pages
        $callCount = 0;
        $this->client->expects($this->exactly(2))
            ->method('getPropertyReport')
            ->willReturnCallback(function ($query, $property) use (&$callCount, $secondReport, $thirdReport) {
                $callCount++;
                if ($callCount === 1) {
                    $this->assertEquals(1, $query['query']['offset']);
                    return $secondReport;
                } else {
                    $this->assertEquals(2, $query['query']['offset']);
                    return $thirdReport;
                }
            });

        $result = $this->paginator->paginate($query, $firstReport, $csvFile);

        $this->assertEquals(3, $result);
    }

    public function testGetOutput(): void
    {
        $this->assertSame($this->output, $this->paginator->getOutput());
    }
}
