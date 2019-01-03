<?php

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Result;

class OutputTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateJsonSampleReportNoData()
    {
        $output = new Output('/tmp/output-test/json-sample-no-data', 'output');
        $query = [
            'query' => [
                'viewId' => 'profile-1',
                'metrics' => [],
                'dimensions' => [],
            ]
        ];
        $report = [
            'data' => []
        ];

        $this->assertEquals([], $output->createSampleReportJson($query, $report));
    }

    public function testCreateJsonSampleReport()
    {
        $output = new Output('/tmp/output-test/json-sample', 'output');
        $query = [
            'query' => [
                'viewId' => 'profile-1',
                'metrics' => [
                    [
                        'expression' => 'ga:exp1'
                    ]
                ],
                'dimensions' => [
                    [
                        'name' => 'ga:dim1'
                    ]
                ],
            ]
        ];
        $report = [
            'data' => [
                new Result(['ga:exp1' => 'met-val1'], ['ga:dim1' => 'dim-val1']),
                new Result(['ga:exp1' => 'met-val2'], ['ga:dim1' => 'dim-val2']),
            ]
        ];

        $this->assertEquals([
            [
                'id' => 'ec5e8bd790552ccc2e0d2474327dbdb8dc7c0ebe',
                'idProfile' => 'profile-1',
                'exp1' => 'met-val1',
                'dim1' => 'dim-val1',
            ],
            [
                'id' => '91cfa51190a338a021222e038f3cef15ffbae06b',
                'idProfile' => 'profile-1',
                'exp1' => 'met-val2',
                'dim1' => 'dim-val2',
            ]
        ], $output->createSampleReportJson($query, $report));
    }

    public function testCreateJsonSampleReportWithDateDimension()
    {
        $output = new Output('/tmp/output-test/json-sample-with-date-dim', 'output');
        $query = [
            'query' => [
                'viewId' => 'profile-1',
                'metrics' => [
                    [
                        'expression' => 'ga:exp1'
                    ]
                ],
                'dimensions' => [
                    [
                        'name' => 'ga:dim1'
                    ],
                    [
                        'name' => 'ga:date'
                    ]
                ],
            ]
        ];
        $report = [
            'data' => [
                new Result(['ga:exp1' => 'met-val1'], ['ga:dim1' => 'dim-val1', 'ga:date' => '2019-01-01']),
                new Result(['ga:exp1' => 'met-val2'], ['ga:dim1' => 'dim-val2', 'ga:date' => '2019-01-02']),
            ]
        ];

        $this->assertEquals([
            [
                'id' => 'fa7617946a9db8f1cb76ce77813ee28f3d90696d',
                'idProfile' => 'profile-1',
                'exp1' => 'met-val1',
                'dim1' => 'dim-val1',
                'date' => '2019-01-01'
            ],
            [
                'id' => '39865a11bfd0cd5f9f31d3b6d359f0108f700caa',
                'idProfile' => 'profile-1',
                'exp1' => 'met-val2',
                'dim1' => 'dim-val2',
                'date' => '2019-01-02'
            ]
        ], $output->createSampleReportJson($query, $report));
    }
}
